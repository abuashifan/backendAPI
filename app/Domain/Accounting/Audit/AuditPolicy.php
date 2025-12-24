<?php

namespace App\Domain\Accounting\Audit;

use App\Models\User;

/**
 * Phase 2 — Step 18: Authorization control (Policy)
 *
 * Audit Policy enforces Step 17 Permission Matrix for audit actions.
 *
 * Accounting rationale:
 * - Audit is ex-post (review & flag), not ex-ante (approval).
 * - Audit actions do NOT affect balances.
 * - Auditors must remain independent: they can mark audit statuses but cannot create/post/reverse journals.
 */
class AuditPolicy
{
    /**
     * View audit status (unchecked/checked/issue_flagged/resolved).
     *
     * Allowed: admin, accounting_staff, auditor
     */
    public function viewAuditStatus(User $user): bool
    {
        return $user->hasPermission('audit.viewStatus');
    }

    /**
     * Mark a journal as audit-checked.
     *
     * Allowed: admin, auditor
     * Not allowed: accounting_staff
     */
    public function auditCheckJournal(User $user): bool
    {
        return $user->hasPermission('audit.check');
    }

    /**
     * Flag an audit issue on a journal.
     *
     * Allowed: admin, auditor
     * Not allowed: accounting_staff
     */
    public function flagAuditIssue(User $user): bool
    {
        return $user->hasPermission('audit.flagIssue');
    }

    /**
     * Mark a previously flagged audit issue as resolved.
     *
     * Allowed: admin
     * Not allowed: accounting_staff, auditor
     */
    public function markAuditResolved(User $user): bool
    {
        return $user->hasPermission('audit.resolve');
    }
}
