<?php

namespace App\Domain\Accounting\Journal;

use App\Models\User;

/**
 * Phase 2 — Step 18: Authorization control (Policy)
 *
 * Journal Policy enforces Step 17 Permission Matrix for journal operations.
 *
 * Important accounting notes:
 * - Journals are posted immediately (no approval workflow).
 * - Posted journals are immutable (no edit, no delete).
 * - Corrections are performed using journal reversal.
 *
 * This policy ONLY controls access; it does not implement accounting rules.
 */
class JournalPolicy
{
    /**
     * View journals.
     *
     * Allowed: admin, accounting_staff, auditor
     */
    public function viewJournal(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accounting_staff', 'auditor']);
    }

    /**
     * Create a journal.
     *
     * Allowed: admin, accounting_staff
     * Not allowed: auditor
     */
    public function createJournal(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accounting_staff']);
    }

    /**
     * Post a journal.
     *
     * Note: In our accounting philosophy, journals are posted immediately,
     * so this permission is equivalent to operational posting capability.
     *
     * Allowed: admin, accounting_staff
     * Not allowed: auditor
     */
    public function postJournal(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accounting_staff']);
    }

    /**
     * Reverse a journal (correction mechanism).
     *
     * Allowed: admin, accounting_staff
     * Not allowed: auditor
     */
    public function reverseJournal(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'accounting_staff']);
    }
}
