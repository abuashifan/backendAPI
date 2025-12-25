<?php

namespace App\Policies;

use App\Models\User;

class CustomerPaymentPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('customer_payment.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer_payment.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('customer_payment.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('customer_payment.delete');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('customer_payment.approve');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('customer_payment.post');
    }
}
