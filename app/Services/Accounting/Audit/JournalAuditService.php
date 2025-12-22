<?php

namespace App\Services\Accounting\Audit;

use App\Models\Journal;
use App\Models\JournalAuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 — Step 19: Audit Flag & Resolution Logic
 *
 * This service ONLY manages audit statuses and audit trail.
 * It must NOT change accounting amounts, posting status, or balances.
 *
 * Audit status is informational:
 * - unchecked, checked, issue_flagged, resolved
 */
class JournalAuditService
{
    /**
     * Mark journal as checked.
     */
    public function check(Journal $journal, User $actor, ?string $note = null): Journal
    {
        return $this->applyAuditAction($journal, $actor, 'checked', $note);
    }

    /**
     * Flag a journal with an audit issue.
     */
    public function flag(Journal $journal, User $actor, ?string $note = null): Journal
    {
        return $this->applyAuditAction($journal, $actor, 'issue_flagged', $note);
    }

    /**
     * Resolve a previously flagged audit issue.
     */
    public function resolve(Journal $journal, User $actor, ?string $note = null): Journal
    {
        return $this->applyAuditAction($journal, $actor, 'resolved', $note);
    }

    private function applyAuditAction(Journal $journal, User $actor, string $newStatus, ?string $note): Journal
    {
        $allowed = ['unchecked', 'checked', 'issue_flagged', 'resolved'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid audit status.');
        }

        return DB::transaction(function () use ($journal, $actor, $newStatus, $note): Journal {
            $journal->refresh();

            $previousStatus = (string) ($journal->audit_status ?? 'unchecked');

            // We do not enforce strict workflow blocking; audit is ex-post.
            // However, "resolved" logically applies to issues. If there is no issue flagged,
            // we still allow admin to mark resolved to keep operations flexible.

            $journal->audit_status = $newStatus;
            $journal->audit_note = $note;
            $journal->audited_by = $actor->id;
            $journal->audited_at = now();
            $journal->save();

            JournalAuditEvent::query()->create([
                'journal_id' => $journal->id,
                'action' => $newStatus,
                'previous_audit_status' => $previousStatus,
                'new_audit_status' => $newStatus,
                'note' => $note,
                'performed_by' => $actor->id,
                'performed_at' => now(),
            ]);

            return $journal;
        });
    }
}
