<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Services\Accounting\Journal\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {
    }

    public function index(): JsonResponse
    {
        $this->authorize('journal.view');

        $journals = Journal::query()
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $journals,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $this->authorize('journal.view');

        $journal = Journal::query()
            ->with('lines')
            ->findOrFail($id);

        return response()->json([
            'data' => $journal,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Journals are posted immediately (no approval flow), so creating a journal
        // requires the journal create capability.
        $this->authorize('journal.create');

        $journalAttributes = (array) $request->input('journal', []);
        $linesAttributes = (array) $request->input('lines', []);

        $journal = $this->journalService->createDraft($journalAttributes, $linesAttributes);
        $journal->load('lines');

        return response()->json([
            'data' => $journal,
        ], 201);
    }
}
