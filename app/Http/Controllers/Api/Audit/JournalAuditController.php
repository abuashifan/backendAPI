<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Services\Accounting\Audit\JournalAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalAuditController extends Controller
{
    public function __construct(
        private readonly JournalAuditService $journalAuditService,
    ) {
    }

    /**
     * POST /journals/{id}/audit/check
     */
    public function check(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('audit.check');

        $validated = $request->validate([
            'audit_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->journalAuditService->check($journal, $request->user(), $validated['audit_note'] ?? null);

        return response()->json([
            'data' => $updated,
        ]);
    }

    /**
     * POST /journals/{id}/audit/flag
     */
    public function flag(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('audit.flagIssue');

        $validated = $request->validate([
            'audit_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->journalAuditService->flag($journal, $request->user(), $validated['audit_note'] ?? null);

        return response()->json([
            'data' => $updated,
        ]);
    }

    /**
     * POST /journals/{id}/audit/resolve
     */
    public function resolve(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('audit.resolve');

        $validated = $request->validate([
            'audit_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->journalAuditService->resolve($journal, $request->user(), $validated['audit_note'] ?? null);

        return response()->json([
            'data' => $updated,
        ]);
    }
}
