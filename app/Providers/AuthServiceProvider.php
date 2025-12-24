<?php

namespace App\Providers;

use App\Domain\Accounting\Audit\AuditPolicy;
use App\Domain\Accounting\Journal\JournalPolicy;
use App\Domain\Accounting\Period\PeriodPolicy;
use App\Domain\Accounting\Report\ReportPolicy;
use App\Domain\System\SystemPolicy;
use App\Models\User;
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

        // Journal Operations
        Gate::define('journal.view', [JournalPolicy::class, 'viewJournal']);
        Gate::define('journal.create', [JournalPolicy::class, 'createJournal']);
        Gate::define('journal.post', [JournalPolicy::class, 'postJournal']);
        Gate::define('journal.reverse', [JournalPolicy::class, 'reverseJournal']);

        // Audit Operations
        Gate::define('audit.viewStatus', [AuditPolicy::class, 'viewAuditStatus']);
        Gate::define('audit.check', [AuditPolicy::class, 'auditCheckJournal']);
        Gate::define('audit.flagIssue', [AuditPolicy::class, 'flagAuditIssue']);
        Gate::define('audit.resolve', [AuditPolicy::class, 'markAuditResolved']);

        // Period Operations
        Gate::define('period.view', [PeriodPolicy::class, 'viewAccountingPeriod']);
        Gate::define('period.close', [PeriodPolicy::class, 'closeAccountingPeriod']);

        // Reporting
        Gate::define('report.trialBalance', [ReportPolicy::class, 'viewTrialBalance']);
        Gate::define('report.generalLedger', [ReportPolicy::class, 'viewGeneralLedger']);

        // System Administration
        Gate::define('system.manageUsers', [SystemPolicy::class, 'manageUsers']);
        Gate::define('system.manageRoles', [SystemPolicy::class, 'manageRoles']);
        Gate::define('system.managePermissions', [SystemPolicy::class, 'managePermissions']);

        // Phase 2 — User-centric permission management
        Gate::define('permission.assign', fn (User $user): bool => $user->hasPermission('permission.assign'));
        Gate::define('permission.copy', fn (User $user): bool => $user->hasPermission('permission.copy'));
    }
}
