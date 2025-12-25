<?php

namespace App\Policies;

use App\Models\User;

class JournalPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermission('journal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('journal.create');
    }

    public function edit(User $user): bool
    {
        return $user->hasPermission('journal.edit');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('journal.delete');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('journal.import');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('journal.export');
    }

    public function approve(User $user): bool
    {
        return $user->hasPermission('journal.approve');
    }

    public function post(User $user): bool
    {
        return $user->hasPermission('journal.post');
    }

    public function reverse(User $user): bool
    {
        return $user->hasPermission('journal.reverse');
    }
}
