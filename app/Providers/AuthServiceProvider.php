<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AuditPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\JournalPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorInvoicePolicy;
use App\Policies\VendorPaymentPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Phase 2 — Policy & Gate (authorization only).
        // Authorization checks must be permission-driven (user-centric).

        // Journal Operations (atomic)
        Gate::define('journal.view', [JournalPolicy::class, 'view']);
        Gate::define('journal.create', [JournalPolicy::class, 'create']);
        Gate::define('journal.edit', [JournalPolicy::class, 'edit']);
        Gate::define('journal.delete', [JournalPolicy::class, 'delete']);
        Gate::define('journal.import', [JournalPolicy::class, 'import']);
        Gate::define('journal.export', [JournalPolicy::class, 'export']);
        Gate::define('journal.approve', [JournalPolicy::class, 'approve']);
        Gate::define('journal.post', [JournalPolicy::class, 'post']);
        Gate::define('journal.reverse', [JournalPolicy::class, 'reverse']);

        // Audit Operations (informational; not approval)
        Gate::define('audit.viewStatus', [AuditPolicy::class, 'viewStatus']);
        Gate::define('audit.check', [AuditPolicy::class, 'check']);
        Gate::define('audit.flagIssue', [AuditPolicy::class, 'flagIssue']);
        Gate::define('audit.resolve', [AuditPolicy::class, 'resolve']);

        // Period Operations
        Gate::define('period.open', [AccountingPeriodPolicy::class, 'open']);
        Gate::define('period.close', [AccountingPeriodPolicy::class, 'close']);

        // Reporting
        Gate::define('report.trialBalance', [ReportPolicy::class, 'trialBalance']);
        Gate::define('report.generalLedger', [ReportPolicy::class, 'generalLedger']);

        // Phase 3 — Step 27: Vendor & Customer master data
        Gate::define('vendor.view', [VendorPolicy::class, 'view']);
        Gate::define('vendor.create', [VendorPolicy::class, 'create']);
        Gate::define('vendor.edit', [VendorPolicy::class, 'edit']);
        Gate::define('vendor.delete', [VendorPolicy::class, 'delete']);

        Gate::define('customer.view', [CustomerPolicy::class, 'view']);
        Gate::define('customer.create', [CustomerPolicy::class, 'create']);
        Gate::define('customer.edit', [CustomerPolicy::class, 'edit']);
        Gate::define('customer.delete', [CustomerPolicy::class, 'delete']);

        // Phase 3 — Step 33: Purchasing API (AP)
        Gate::define('purchase_order.view', [PurchaseOrderPolicy::class, 'view']);
        Gate::define('purchase_order.create', [PurchaseOrderPolicy::class, 'create']);
        Gate::define('purchase_order.edit', [PurchaseOrderPolicy::class, 'edit']);
        Gate::define('purchase_order.delete', [PurchaseOrderPolicy::class, 'delete']);
        Gate::define('purchase_order.approve', [PurchaseOrderPolicy::class, 'approve']);
        Gate::define('purchase_order.cancel', [PurchaseOrderPolicy::class, 'cancel']);

        Gate::define('vendor_invoice.view', [VendorInvoicePolicy::class, 'view']);
        Gate::define('vendor_invoice.create', [VendorInvoicePolicy::class, 'create']);
        Gate::define('vendor_invoice.edit', [VendorInvoicePolicy::class, 'edit']);
        Gate::define('vendor_invoice.delete', [VendorInvoicePolicy::class, 'delete']);
        Gate::define('vendor_invoice.approve', [VendorInvoicePolicy::class, 'approve']);
        Gate::define('vendor_invoice.post', [VendorInvoicePolicy::class, 'post']);

        Gate::define('vendor_payment.view', [VendorPaymentPolicy::class, 'view']);
        Gate::define('vendor_payment.create', [VendorPaymentPolicy::class, 'create']);
        Gate::define('vendor_payment.edit', [VendorPaymentPolicy::class, 'edit']);
        Gate::define('vendor_payment.delete', [VendorPaymentPolicy::class, 'delete']);
        Gate::define('vendor_payment.approve', [VendorPaymentPolicy::class, 'approve']);
        Gate::define('vendor_payment.post', [VendorPaymentPolicy::class, 'post']);

        // System / Access Control (user-centric)
        Gate::define('user.manage', [UserPolicy::class, 'manage']);
        Gate::define('permission.assign', fn (User $user): bool => $user->hasPermission('permission.assign'));
        Gate::define('permission.copy', fn (User $user): bool => $user->hasPermission('permission.copy'));
    }
}
