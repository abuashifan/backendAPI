<?php

namespace App\Services\Accounting\Report;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * Trial Balance for a single accounting period.
     *
     * Data sources (strict): chart_of_accounts, journals, journal_lines.
     * Filters (strict): journals.status = 'posted' AND journals.period_id = :periodId.
     * Accounts with no journal lines must still appear with zero totals.
     */
    public function trialBalance(int $periodId): Collection
    {
        return DB::table('chart_of_accounts as coa')
            ->leftJoin('journal_lines as jl', 'jl.account_id', '=', 'coa.id')
            ->leftJoin('journals as j', function ($join) use ($periodId) {
                $join->on('j.id', '=', 'jl.journal_id')
                    ->where('j.status', '=', 'posted')
                    ->where('j.period_id', '=', $periodId);
            })
            ->groupBy('coa.id', 'coa.code', 'coa.name')
            ->orderBy('coa.code')
            ->select([
                'coa.id as account_id',
                'coa.code as account_code',
                'coa.name as account_name',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN jl.debit ELSE 0 END), 0) as total_debit"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN jl.credit ELSE 0 END), 0) as total_credit"
            )
            ->selectRaw(
                "(
                    COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN jl.debit ELSE 0 END), 0)
                    -
                    COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN jl.credit ELSE 0 END), 0)
                ) as balance"
            )
            ->get();
    }
}
