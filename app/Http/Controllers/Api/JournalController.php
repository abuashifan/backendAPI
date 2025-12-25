<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Services\Accounting\Journal\ApproveJournalService;
use App\Services\Accounting\Journal\JournalService;
use App\Services\Accounting\Journal\PostJournalService;
use App\Services\Accounting\Journal\ReverseJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly ApproveJournalService $approveJournalService,
        private readonly PostJournalService $postJournalService,
        private readonly ReverseJournalService $reverseJournalService,
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
        // Creating a journal draft requires the journal create capability.
        $this->authorize('journal.create');

        $validated = $request->validate([
            'journal' => ['required', 'array'],
            'journal.journal_number' => ['required', 'string'],
            'journal.company_id' => ['required', 'integer', 'exists:companies,id'],
            'journal.period_id' => ['required', 'integer', 'exists:accounting_periods,id'],
            'journal.journal_date' => ['required', 'date'],
            'journal.source_type' => ['required', 'string'],
            'journal.source_id' => ['nullable', 'integer'],
            'journal.description' => ['required', 'string'],

            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.department_id' => ['nullable', 'integer'],
            'lines.*.project_id' => ['nullable', 'integer'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $journalAttributes = (array) $validated['journal'];
        $linesAttributes = (array) $validated['lines'];

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $journal = $this->journalService->createDraft($actor, $journalAttributes, $linesAttributes);
            $journal->load('lines');
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $journal,
        ], 201);
    }

    /**
     * POST /journals/{journal}/approve
     */
    public function approve(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('journal.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $approved = $this->approveJournalService->approve($journal, $actor);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    /**
     * POST /journals/{journal}/post
     */
    public function post(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('journal.post');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $posted = $this->postJournalService->post($journal, $actor);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $posted,
        ]);
    }

    /**
     * POST /journals/{journal}/reverse
     */
    public function reverse(Request $request, Journal $journal): JsonResponse
    {
        $this->authorize('journal.reverse');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'description' => ['nullable', 'string'],
        ]);

        try {
            $reversal = $this->reverseJournalService->reverse($journal, $actor, $validated['description'] ?? null);
            $reversal->load('lines');
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $reversal,
        ], 201);
    }
}
