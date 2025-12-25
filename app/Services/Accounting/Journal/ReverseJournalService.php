<?php

namespace App\Services\Accounting\Journal;

use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ReverseJournalService
{
    public function reverse(Journal $journal, User $actor, ?string $description = null): Journal
    {
        return DB::transaction(function () use ($journal, $actor, $description): Journal {
            $journal->refresh();
            $journal->loadMissing(['lines', 'period']);

            $old = [
                'status' => $journal->status,
                'reversed_by' => $journal->reversed_by,
                'reversed_at' => $journal->reversed_at?->toISOString(),
            ];

            if ($journal->status !== 'posted') {
                throw new \DomainException('Only posted journals can be reversed.');
            }

            /** @var AccountingPeriod|null $period */
            $period = $journal->period;
            if ($period === null || $period->status !== 'open') {
                throw new \DomainException('Accounting period is closed.');
            }

            $reversalNumber = $this->reversalNumber($journal);

            $reversal = Journal::create([
                'journal_number' => $reversalNumber,
                'company_id' => $journal->company_id,
                'period_id' => $journal->period_id,
                'journal_date' => $journal->journal_date,
                'source_type' => 'reversal',
                'source_id' => $journal->id,
                'description' => $description ?: ('Reversal of journal #' . $journal->id),
                'status' => 'posted',
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'posted_by' => $actor->id,
                'posted_at' => now(),
                'reversal_of_journal_id' => $journal->id,
            ]);

            foreach ($journal->lines as $line) {
                JournalLine::create([
                    'journal_id' => $reversal->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'department_id' => $line->department_id,
                    'project_id' => $line->project_id,
                    'description' => $line->description,
                ]);
            }

            // Mark the original as reversed (immutable; no line edits).
            $journal->status = 'reversed';
            $journal->reversed_by = $actor->id;
            $journal->reversed_at = now();
            $journal->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'journal.reverse',
                table: 'journals',
                recordId: (int) $journal->id,
                oldValue: $old,
                newValue: [
                    'status' => $journal->status,
                    'reversed_by' => $journal->reversed_by,
                    'reversed_at' => $journal->reversed_at?->toISOString(),
                    'reversal_journal_id' => $reversal->id,
                ],
            );

            return $reversal;
        });
    }

    private function reversalNumber(Journal $journal): string
    {
        $base = $journal->journal_number;
        $candidate = $base . '-REV';

        // Ensure uniqueness per company.
        $exists = Journal::query()
            ->where('company_id', $journal->company_id)
            ->where('journal_number', $candidate)
            ->exists();

        if (!$exists) {
            return $candidate;
        }

        return $base . '-REV-' . $journal->id;
    }
}
