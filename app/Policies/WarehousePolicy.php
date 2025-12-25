<?php

namespace App\Policies;

use App\Models\User;

class WarehousePolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('warehouse.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('warehouse.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('warehouse.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('warehouse.delete');
    }
}
