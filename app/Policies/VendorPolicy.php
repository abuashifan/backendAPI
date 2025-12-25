<?php

namespace App\Policies;

use App\Models\User;

class VendorPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('vendor.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendor.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('vendor.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('vendor.delete');
    }
}
