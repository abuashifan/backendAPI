<?php

namespace App\Policies;

use App\Models\User;

class PurchaseOrderPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('purchase_order.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_order.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('purchase_order.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('purchase_order.delete');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('purchase_order.approve');
    }

    public function cancel(User $user): bool
    {
        return $user->hasPermission('purchase_order.cancel');
    }
}
