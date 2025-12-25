<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function trialBalance(User $user): bool
    {
        return $user->hasPermission('report.trial_balance.view');
    }

    public function generalLedger(User $user): bool
    {
        return $user->hasPermission('report.general_ledger.view');
    }
}
