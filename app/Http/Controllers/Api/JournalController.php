<?php

namespace App\Http\Controllers\Api;

use App\Models\Journal;
use App\Services\Accounting\Journal\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class JournalController extends BaseController
{
    public function __construct(
        private readonly JournalService $journalService,
    ) {
    }

    public function index(): JsonResponse
    {
        $journals = Journal::query()
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $journals,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $journal = Journal::query()
            ->with('lines')
            ->findOrFail($id);

        return response()->json([
            'data' => $journal,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $journalAttributes = (array) $request->input('journal', []);
        $linesAttributes = (array) $request->input('lines', []);

        $journal = $this->journalService->createDraft($journalAttributes, $linesAttributes);
        $journal->load('lines');

        return response()->json([
            'data' => $journal,
        ], 201);
    }
}
