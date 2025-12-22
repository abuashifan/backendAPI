<?php

namespace App\Domain\System;

use App\Models\User;

/**
 * Phase 2 — Step 18: Authorization control (Policy)
 *
 * System Policy controls system administration capabilities.
 *
 * Accounting rationale:
 * - Managing users/roles/permissions changes internal control structure.
 * - This must remain with accounting leadership (admin).
 */
class SystemPolicy
{
    /**
     * Manage users.
     *
     * Allowed: admin
     */
    public function manageUsers(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Manage roles.
     *
     * Allowed: admin
     */
    public function manageRoles(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Manage permissions.
     *
     * Allowed: admin
     */
    public function managePermissions(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
