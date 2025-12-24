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
            // Journals
            'journal.view',
            'journal.create',
            'journal.edit',
            'journal.delete',
            'journal.import',
            'journal.export',
            'journal.approve',
            'journal.post',
            'journal.reverse',

            // Accounting periods
            'period.view',
            'period.close',
            'period.reopen',

            // Audit / Activity
            'audit.view',
            'audit.log.view',

            // Audit abilities (used by existing API endpoints/gates)
            'audit.viewStatus',
            'audit.check',
            'audit.flagIssue',
            'audit.resolve',

            // Reporting
            'report.trial_balance.view',
            'report.general_ledger.view',
            'report.financial_statements.view',

            // System / Access Control
            'user.view',
            'user.create',
            'user.edit',
            'user.deactivate',
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
            'permission.view',
            'permission.assign',
            'permission.copy',
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
