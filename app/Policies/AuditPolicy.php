<?php

namespace App\Policies;

use App\Models\User;

class AuditPolicy
{
    public function viewStatus(User $user): bool
    {
        return $user->hasPermission('audit.viewStatus');
    }

    public function check(User $user): bool
    {
        return $user->hasPermission('audit.check');
    }

    public function flagIssue(User $user): bool
    {
        return $user->hasPermission('audit.flagIssue');
    }

    public function resolve(User $user): bool
    {
        return $user->hasPermission('audit.resolve');
    }
}
