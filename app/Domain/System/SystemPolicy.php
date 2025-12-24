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
        return $user->hasAnyPermission([
            'user.view',
            'user.create',
            'user.edit',
            'user.deactivate',
            'permission.assign',
            'permission.copy',
        ]);
    }

    /**
     * Manage roles.
     *
     * Allowed: admin
     */
    public function manageRoles(User $user): bool
    {
        return $user->hasAnyPermission([
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
        ]);
    }

    /**
     * Manage permissions.
     *
     * Allowed: admin
     */
    public function managePermissions(User $user): bool
    {
        return $user->hasAnyPermission([
            'permission.view',
            'permission.assign',
        ]);
    }
}
