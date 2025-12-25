<?php

namespace App\Policies;

use App\Models\User;

class CustomerPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('customer.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('customer.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('customer.delete');
    }
}
