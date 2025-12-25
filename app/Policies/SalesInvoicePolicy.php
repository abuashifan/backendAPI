<?php

namespace App\Policies;

use App\Models\User;

class SalesInvoicePolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('sales_invoice.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sales_invoice.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('sales_invoice.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('sales_invoice.delete');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('sales_invoice.approve');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('sales_invoice.post');
    }
}
