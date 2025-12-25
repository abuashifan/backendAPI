<?php

namespace App\Services\Accounting\Journal;

use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PostJournalService
{
    public function post(Journal $journal, User $actor): Journal
    {
        return DB::transaction(function () use ($journal, $actor): Journal {
            $journal->refresh();
            $journal->loadMissing(['lines', 'period']);

            $old = [
                'status' => $journal->status,
                'approved_by' => $journal->approved_by,
                'approved_at' => $journal->approved_at?->toISOString(),
                'posted_by' => $journal->posted_by,
                'posted_at' => $journal->posted_at?->toISOString(),
            ];

            if (in_array($journal->status, ['posted', 'reversed'], true)) {
                throw new \DomainException('Journal is not postable.');
            }

            if (!in_array($journal->status, ['draft', 'approved'], true)) {
                throw new \DomainException('Invalid journal status.');
            }

            /** @var AccountingPeriod|null $period */
            $period = $journal->period;
            if ($period === null || $period->status !== 'open') {
                throw new \DomainException('Accounting period is closed.');
            }

            $totals = $this->totals($journal);
            if ($totals['debit'] !== $totals['credit']) {
                throw new \DomainException('Journal is not balanced (debit must equal credit).');
            }

            // Phase 2 auto-approve logic:
            // - If user has journal.post but not journal.approve, system still approves during post.
            if ($journal->approved_at === null) {
                $journal->approved_by = $actor->id;
                $journal->approved_at = now();
            }

            $journal->status = 'posted';
            $journal->posted_by = $actor->id;
            $journal->posted_at = now();
            $journal->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'journal.post',
                table: 'journals',
                recordId: (int) $journal->id,
                oldValue: $old,
                newValue: [
                    'status' => $journal->status,
                    'approved_by' => $journal->approved_by,
                    'approved_at' => $journal->approved_at?->toISOString(),
                    'posted_by' => $journal->posted_by,
                    'posted_at' => $journal->posted_at?->toISOString(),
                ],
            );

            return $journal;
        });
    }

    /**
     * @return array{debit:string, credit:string}
     */
    private function totals(Journal $journal): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($journal->lines as $line) {
            $debit += (float) $line->debit;
            $credit += (float) $line->credit;
        }

        // Normalize to 2 decimals for comparison.
        return [
            'debit' => number_format(round($debit, 2), 2, '.', ''),
            'credit' => number_format(round($credit, 2), 2, '.', ''),
        ];
    }
}
