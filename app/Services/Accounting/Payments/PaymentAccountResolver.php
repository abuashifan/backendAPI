<?php

namespace App\Services\Accounting\Payments;

use App\Models\ChartOfAccount;

class PaymentAccountResolver
{
    public function resolveCashOrBankAccount(int $companyId, string $method): ChartOfAccount
    {
        $normalized = strtolower(trim($method));

        // Minimal convention:
        // - 'cash' => Cash (1-1100)
        // - anything else => Bank (1-1200)
        $code = $normalized === 'cash' ? '1-1100' : '1-1200';

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($account === null) {
            throw new \DomainException('Missing required Chart of Account ' . $code . ' for company.');
        }

        if (!$account->is_postable) {
            throw new \DomainException('Chart of Account ' . $code . ' is not postable.');
        }

        return $account;
    }
}
