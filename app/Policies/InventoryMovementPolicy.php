<?php

namespace App\Policies;

use App\Models\User;

class InventoryMovementPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('inventory_movement.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory_movement.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('inventory_movement.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('inventory_movement.delete');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('inventory_movement.post');
    }
}
