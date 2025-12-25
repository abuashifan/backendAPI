<?php

namespace App\Policies;

use App\Models\User;

class VendorPaymentPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('vendor_payment.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendor_payment.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('vendor_payment.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('vendor_payment.delete');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('vendor_payment.approve');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('vendor_payment.post');
    }
}
