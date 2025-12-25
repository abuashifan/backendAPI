<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('product.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('product.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('product.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('product.delete');
    }
}
