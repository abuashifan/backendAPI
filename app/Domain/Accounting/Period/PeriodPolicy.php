<?php

namespace App\Domain\Accounting\Period;

use App\Models\User;

/**
 * Phase 2 — Step 18: Authorization control (Policy)
 *
 * Period Policy enforces who can view and close accounting periods.
 *
 * Accounting rationale:
 * - Closing a period is a high-control action and belongs to accounting leadership.
 * - Other roles may view periods for operational awareness and audit planning.
 */
class PeriodPolicy
{
    /**
     * View accounting periods.
     *
     * Allowed: admin, accounting_staff, auditor
     */
    public function viewAccountingPeriod(User $user): bool
    {
        return $user->hasPermission('period.view');
    }

    /**
     * Close an accounting period.
     *
     * Allowed: admin
     * Not allowed: accounting_staff, auditor
     */
    public function closeAccountingPeriod(User $user): bool
    {
        return $user->hasPermission('period.close');
    }
}
