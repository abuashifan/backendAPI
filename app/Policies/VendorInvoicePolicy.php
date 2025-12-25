<?php

namespace App\Policies;

use App\Models\User;

class VendorInvoicePolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.delete');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.approve');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('vendor_invoice.post');
    }
}
