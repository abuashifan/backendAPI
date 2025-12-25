<?php

namespace App\Policies;

use App\Models\User;

class AccountingPeriodPolicy
{
    public function open(User $user): bool
    {
        return $user->hasPermission('period.open');
    }

    public function close(User $user): bool
    {
        return $user->hasPermission('period.close');
    }
}
