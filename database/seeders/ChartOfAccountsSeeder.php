<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $company = DB::table('companies')->first();
        if (!$company) {
            throw new RuntimeException('No company found. Please seed companies table first.');
        }
        $companyId = $company->id;

        $accounts = [
            // ASSETS
            ['code' => '1-0000', 'name' => 'Assets', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '1-1000', 'name' => 'Cash & Cash Equivalents', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-0000', 'level' => 1, 'is_postable' => false],
            ['code' => '1-1100', 'name' => 'Cash', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-1000', 'level' => 2, 'is_postable' => true],
            ['code' => '1-1200', 'name' => 'Bank', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-1000', 'level' => 2, 'is_postable' => true],
            ['code' => '1-1300', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '1-1400', 'name' => 'Inventory', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '1-1500', 'name' => 'Fixed Assets', 'type' => 'asset', 'normal_balance' => 'debit', 'parent_code' => '1-0000', 'level' => 1, 'is_postable' => false],
            ['code' => '1-1510', 'name' => 'Accumulated Depreciation – Fixed Assets', 'type' => 'asset', 'normal_balance' => 'credit', 'parent_code' => '1-1500', 'level' => 2, 'is_postable' => true],
            // LIABILITIES
            ['code' => '2-0000', 'name' => 'Liabilities', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '2-1100', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_code' => '2-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '2-1200', 'name' => 'Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit', 'parent_code' => '2-0000', 'level' => 2, 'is_postable' => true],
            // EQUITY
            ['code' => '3-0000', 'name' => 'Equity', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '3-1100', 'name' => 'Owner Capital', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_code' => '3-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '3-1200', 'name' => 'Retained Earnings', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_code' => '3-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '3-1300', 'name' => 'Opening Balance Equity', 'type' => 'equity', 'normal_balance' => 'credit', 'parent_code' => '3-0000', 'level' => 2, 'is_postable' => true],
            // INCOME
            ['code' => '4-0000', 'name' => 'Revenue', 'type' => 'income', 'normal_balance' => 'credit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '4-1100', 'name' => 'Sales Revenue', 'type' => 'income', 'normal_balance' => 'credit', 'parent_code' => '4-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '4-1200', 'name' => 'Other Income', 'type' => 'income', 'normal_balance' => 'credit', 'parent_code' => '4-0000', 'level' => 2, 'is_postable' => true],
            // COGS / EXPENSE
            ['code' => '5-0000', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '5-1100', 'name' => 'Cost of Goods Sold', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => '5-0000', 'level' => 2, 'is_postable' => true],
            // OPERATING EXPENSES
            ['code' => '6-0000', 'name' => 'Operating Expenses', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => null, 'level' => 1, 'is_postable' => false],
            ['code' => '6-1100', 'name' => 'Salary Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => '6-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '6-1200', 'name' => 'Rent Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => '6-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '6-1300', 'name' => 'Utilities Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => '6-0000', 'level' => 2, 'is_postable' => true],
            ['code' => '6-1400', 'name' => 'Depreciation Expense', 'type' => 'expense', 'normal_balance' => 'debit', 'parent_code' => '6-0000', 'level' => 2, 'is_postable' => true],
        ];

        $codeToId = [];
        foreach ($accounts as $account) {
            $parentId = $account['parent_code'] ? ($codeToId[$account['parent_code']] ?? null) : null;
            $coa = DB::table('chart_of_accounts')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'code' => $account['code'],
                ],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'parent_id' => $parentId,
                    'level' => $account['level'],
                    'is_postable' => $account['is_postable'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            // Ambil id setelah insert/update
            $row = DB::table('chart_of_accounts')
                ->where('company_id', $companyId)
                ->where('code', $account['code'])
                ->first();
            $codeToId[$account['code']] = $row->id;
        }
    }
}
