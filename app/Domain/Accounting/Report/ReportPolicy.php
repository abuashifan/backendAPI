<?php

namespace App\Domain\Accounting\Report;

use App\Models\User;

/**
 * Phase 2 — Step 18: Authorization control (Policy)
 *
 * Report Policy controls access to accounting reports.
 *
 * Accounting rationale:
 * - Trial Balance and General Ledger are day-to-day reconciliation tools.
 * - Financial Statements are management-level outputs, also needed by auditors for independent review.
 */
class ReportPolicy
{
    /**
     * View Trial Balance.
     *
     * Allowed: admin, accounting_staff, auditor
     */
    public function viewTrialBalance(User $user): bool
    {
        return $user->hasPermission('report.trial_balance.view');
    }

    /**
     * View General Ledger.
     *
     * Allowed: admin, accounting_staff, auditor
     */
    public function viewGeneralLedger(User $user): bool
    {
        return $user->hasPermission('report.general_ledger.view');
    }

    /**
     * View Financial Statements.
     *
     * Allowed: admin, auditor
     * Not allowed: accounting_staff
     */
    public function viewFinancialStatements(User $user): bool
    {
        return $user->hasPermission('report.financial_statements.view');
    }
}
