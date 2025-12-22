<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditIssueController extends Controller
{
    /**
     * GET /audits/issues
     *
     * Returns journals filtered by audit_status.
     * Audit status is informational and does NOT affect balances.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('audit.viewStatus');

        $validated = $request->validate([
            'audit_status' => ['nullable', 'string', 'in:unchecked,checked,issue_flagged,resolved'],
        ]);

        $auditStatus = $validated['audit_status'] ?? 'issue_flagged';

        $journals = Journal::query()
            ->where('audit_status', $auditStatus)
            ->orderByDesc('audited_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $journals,
        ]);
    }
}
