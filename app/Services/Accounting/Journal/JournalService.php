<?php

namespace App\Services\Accounting\Journal;

use App\Models\Journal;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

class JournalService
{
    /**
     * Create a draft journal (header + lines).
     *
     * No validation, no posting, no accounting rules.
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
     *   created_by:int,
     *   posted_at?:string|\DateTimeInterface|null
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
    public function createDraft(array $journalAttributes, array $linesAttributes): Journal
    {
        return DB::transaction(function () use ($journalAttributes, $linesAttributes): Journal {
            $journal = Journal::create([
                'journal_number' => $journalAttributes['journal_number'],
                'company_id' => $journalAttributes['company_id'],
                'period_id' => $journalAttributes['period_id'],
                'journal_date' => $journalAttributes['journal_date'],
                'source_type' => $journalAttributes['source_type'],
                'source_id' => $journalAttributes['source_id'] ?? null,
                'description' => $journalAttributes['description'],
                'status' => 'draft',
                'created_by' => $journalAttributes['created_by'],
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

            return $journal;
        });
    }
}
