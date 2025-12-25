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

            // Phase 3 — Step 27: Vendor & Customer master data
            'vendor.view',
            'vendor.create',
            'vendor.edit',
            'vendor.delete',
            'customer.view',
            'customer.create',
            'customer.edit',
            'customer.delete',

            // Phase 4 — Step 36: Product & Warehouse master data
            'product.view',
            'product.create',
            'product.edit',
            'product.delete',

            'warehouse.view',
            'warehouse.create',
            'warehouse.edit',
            'warehouse.delete',

            // Phase 3 — Step 33: Purchasing (AP) API
            'purchase_order.view',
            'purchase_order.create',
            'purchase_order.edit',
            'purchase_order.delete',
            'purchase_order.approve',
            'purchase_order.cancel',

            'vendor_invoice.view',
            'vendor_invoice.create',
            'vendor_invoice.edit',
            'vendor_invoice.delete',
            'vendor_invoice.approve',
            'vendor_invoice.post',

            'vendor_payment.view',
            'vendor_payment.create',
            'vendor_payment.edit',
            'vendor_payment.delete',
            'vendor_payment.approve',
            'vendor_payment.post',

            // Phase 3 — Step 34: Sales (AR) API
            'sales_invoice.view',
            'sales_invoice.create',
            'sales_invoice.edit',
            'sales_invoice.delete',
            'sales_invoice.approve',
            'sales_invoice.post',

            'customer_payment.view',
            'customer_payment.create',
            'customer_payment.edit',
            'customer_payment.delete',
            'customer_payment.approve',
            'customer_payment.post',

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
