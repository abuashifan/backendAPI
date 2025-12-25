<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed atomic, action-based permissions.
     *
     * Rules:
     * - Permissions are standalone entities (independent of roles).
     * - Idempotent: safe to run multiple times.
     */
    public function run(): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        // Use the app's default auth guard to avoid hard-coding guard assumptions.
        $guardName = (string) config('auth.defaults.guard', 'web');

        $permissions = [
            // Journals (required minimum)
            'journal.view',
            'journal.create',
            'journal.edit',
            'journal.delete',
            'journal.import',
            'journal.export',
            'journal.approve',
            'journal.post',
            'journal.reverse',

            // System (required minimum)
            'user.manage',
            'permission.assign',
            'period.open',
            'period.close',

            // System helpers (used by existing API)
            'permission.copy',

            // Audit actions (used by existing API endpoints)
            'audit.viewStatus',
            'audit.check',
            'audit.flagIssue',
            'audit.resolve',

            // Reporting (Phase 2 output)
            'report.trial_balance.view',
            'report.general_ledger.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guardName,
            ]);
        }

        $registrar->forgetCachedPermissions();
    }
}
