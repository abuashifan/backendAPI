<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

class AccountingPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('companies')->first();
        if (!$company) {
            throw new RuntimeException('No company found. Please seed companies table first.');
        }
        $companyId = $company->id;
        $year = 2025;
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();
            DB::table('accounting_periods')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'status' => 'open',
                    'closed_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
