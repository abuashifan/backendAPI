<?php

namespace App\Http\Controllers\Api\Accounting\AR;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Services\Accounting\AR\ApproveSalesInvoiceService;
use App\Services\Accounting\AR\PostSalesInvoiceService;
use App\Services\Accounting\AR\SalesInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly ApproveSalesInvoiceService $approveSalesInvoiceService,
        private readonly PostSalesInvoiceService $postSalesInvoiceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('sales_invoice.view');

        $query = SalesInvoice::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        $customerId = $request->query('customer_id');
        if ($customerId !== null) {
            $query->where('customer_id', (int) $customerId);
        }

        return response()->json([
            'data' => $query->with(['lines'])->get(),
        ]);
    }

    public function show(SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('sales_invoice.view');

        $salesInvoice->loadMissing(['lines']);

        return response()->json([
            'data' => $salesInvoice,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('sales_invoice.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_number' => ['required', 'string'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.tax_id' => ['nullable', 'integer'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $invoice = $this->salesInvoiceService->createDraft(
                invoiceAttributes: $validated,
                linesAttributes: $validated['lines'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $invoice->loadMissing(['lines']);

        return response()->json([
            'data' => $invoice,
        ], 201);
    }

    public function update(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('sales_invoice.edit');

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'invoice_number' => ['sometimes', 'string'],
            'invoice_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'tax_amount' => ['nullable', 'numeric'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', 'integer'],
            'lines.*.description' => ['required', 'string'],
            'lines.*.qty' => ['required', 'numeric'],
            'lines.*.unit_price' => ['required', 'numeric'],
            'lines.*.tax_id' => ['nullable', 'integer'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $updated = $this->salesInvoiceService->updateDraft(
                invoice: $salesInvoice,
                invoiceAttributes: $validated,
                linesAttributes: $validated['lines'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $updated->loadMissing(['lines']);

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('sales_invoice.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->salesInvoiceService->deleteDraft($salesInvoice, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function approve(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('sales_invoice.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $approved = $this->approveSalesInvoiceService->approve($salesInvoice, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    public function post(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $this->authorize('sales_invoice.post');

        $validated = $request->validate([
            'auto_approve' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $journal = $this->postSalesInvoiceService->post(
                invoice: $salesInvoice,
                actor: $actor,
                autoApproveIfNeeded: (bool) ($validated['auto_approve'] ?? false),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $salesInvoice->refresh();

        return response()->json([
            'data' => [
                'invoice' => $salesInvoice,
                'journal' => $journal,
            ],
        ]);
    }
}
