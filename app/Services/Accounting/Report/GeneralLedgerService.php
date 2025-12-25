<?php

namespace App\Services\Accounting\Report;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * General Ledger for a single accounting period.
     *
     * Data sources (strict): journals, journal_lines, chart_of_accounts.
     * Filters (strict): journals.status = 'posted' AND journals.period_id = :periodId.
     */
    public function generalLedger(int $periodId): Collection
    {
        return DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jl.account_id')
            ->where('j.status', '=', 'posted')
            ->where('j.period_id', '=', $periodId)
            ->orderBy('coa.code')
            ->orderBy('j.journal_date')
            ->orderBy('j.id')
            ->orderBy('jl.id')
            ->select([
                'coa.id as account_id',
                'coa.code as account_code',
                'coa.name as account_name',
                'j.id as journal_id',
                'j.journal_number',
                'j.journal_date',
                'j.description as journal_description',
                'jl.id as journal_line_id',
                'jl.description as line_description',
                'jl.debit',
                'jl.credit',
            ])
            ->get();
    }
}
