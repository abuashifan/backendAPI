<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AuditPolicy;
use App\Policies\JournalPolicy;
use App\Policies\ReportPolicy;
use App\Policies\UserPolicy;
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

        // System / Access Control (user-centric)
        Gate::define('user.manage', [UserPolicy::class, 'manage']);
        Gate::define('permission.assign', fn (User $user): bool => $user->hasPermission('permission.assign'));
        Gate::define('permission.copy', fn (User $user): bool => $user->hasPermission('permission.copy'));
    }
}
