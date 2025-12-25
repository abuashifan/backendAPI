<?php

namespace App\Services\Accounting\Journal;

use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Create a draft journal (header + lines).
     *
        * Phase 2 validations:
        * - Period must be OPEN
        * - Debit must equal credit
     * Status is always forced to "draft".
     *
     * @param  array{
     *   journal_number:string,
     *   company_id:int,
     *   period_id:int,
     *   journal_date:string|\DateTimeInterface,
     *   source_type:string,
     *   source_id?:int|null,
     *   description:string,
    *   created_by:int
     * }  $journalAttributes
     * @param  array<int, array{
     *   account_id:int,
     *   debit?:string|int|float,
     *   credit?:string|int|float,
     *   department_id?:int|null,
     *   project_id?:int|null,
     *   description?:string|null
     * }>  $linesAttributes
     */
    public function createDraft(User $actor, array $journalAttributes, array $linesAttributes): Journal
    {
        return DB::transaction(function () use ($actor, $journalAttributes, $linesAttributes): Journal {
            $period = AccountingPeriod::query()->find($journalAttributes['period_id']);
            if ($period === null || $period->status !== 'open') {
                throw new \DomainException('Accounting period is closed.');
            }

            $totals = $this->totals($linesAttributes);
            if ($totals['debit'] !== $totals['credit']) {
                throw new \DomainException('Journal is not balanced (debit must equal credit).');
            }

            if ((bool) config('accounting.budget_enabled', false)) {
                // Hook: if budgeting is enabled in the future, enforce checks here.
                throw new \DomainException('Budget check is enabled but not implemented.');
            }

            $journal = Journal::create([
                'journal_number' => $journalAttributes['journal_number'],
                'company_id' => $journalAttributes['company_id'],
                'period_id' => $journalAttributes['period_id'],
                'journal_date' => $journalAttributes['journal_date'],
                'source_type' => $journalAttributes['source_type'],
                'source_id' => $journalAttributes['source_id'] ?? null,
                'description' => $journalAttributes['description'],
                'status' => 'draft',
                'created_by' => $actor->id,
                'posted_at' => null,
            ]);

            foreach ($linesAttributes as $lineAttributes) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'account_id' => $lineAttributes['account_id'],
                    'debit' => $lineAttributes['debit'] ?? 0,
                    'credit' => $lineAttributes['credit'] ?? 0,
                    'department_id' => $lineAttributes['department_id'] ?? null,
                    'project_id' => $lineAttributes['project_id'] ?? null,
                    'description' => $lineAttributes['description'] ?? null,
                ]);
            }

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'journal.create',
                table: 'journals',
                recordId: (int) $journal->id,
                oldValue: null,
                newValue: [
                    'status' => $journal->status,
                    'company_id' => $journal->company_id,
                    'period_id' => $journal->period_id,
                ],
            );

            return $journal;
        });
    }

    /**
     * @param  array<int, array{debit?:string|int|float, credit?:string|int|float}>  $lines
     * @return array{debit:string, credit:string}
     */
    private function totals(array $lines): array
    {
        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        return [
            'debit' => number_format(round($debit, 2), 2, '.', ''),
            'credit' => number_format(round($credit, 2), 2, '.', ''),
        ];
    }

    /**
     * Delete a journal only when it is still mutable.
     */
    public function deleteDraft(Journal $journal): void
    {
        if ($journal->status !== 'draft') {
            throw new \DomainException('Only draft journals can be deleted.');
        }

        $journal->delete();
    }
}
