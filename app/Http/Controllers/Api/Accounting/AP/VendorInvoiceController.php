<?php

namespace App\Http\Controllers\Api\Accounting\AP;

use App\Http\Controllers\Controller;
use App\Models\VendorInvoice;
use App\Services\Accounting\AP\ApproveVendorInvoiceService;
use App\Services\Accounting\AP\PostVendorInvoiceService;
use App\Services\Accounting\AP\VendorInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorInvoiceController extends Controller
{
    public function __construct(
        private readonly VendorInvoiceService $vendorInvoiceService,
        private readonly ApproveVendorInvoiceService $approveVendorInvoiceService,
        private readonly PostVendorInvoiceService $postVendorInvoiceService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('vendor_invoice.view');

        $query = VendorInvoice::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        $vendorId = $request->query('vendor_id');
        if ($vendorId !== null) {
            $query->where('vendor_id', (int) $vendorId);
        }

        return response()->json([
            'data' => $query->with(['lines'])->get(),
        ]);
    }

    public function show(VendorInvoice $vendorInvoice): JsonResponse
    {
        $this->authorize('vendor_invoice.view');

        $vendorInvoice->loadMissing(['lines']);

        return response()->json([
            'data' => $vendorInvoice,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('vendor_invoice.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
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
            $invoice = $this->vendorInvoiceService->createDraft(
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

    public function update(Request $request, VendorInvoice $vendorInvoice): JsonResponse
    {
        $this->authorize('vendor_invoice.edit');

        $validated = $request->validate([
            'vendor_id' => ['sometimes', 'integer', 'exists:vendors,id'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
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
            $updated = $this->vendorInvoiceService->updateDraft(
                invoice: $vendorInvoice,
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

    public function destroy(Request $request, VendorInvoice $vendorInvoice): JsonResponse
    {
        $this->authorize('vendor_invoice.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->vendorInvoiceService->deleteDraft($vendorInvoice, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function approve(Request $request, VendorInvoice $vendorInvoice): JsonResponse
    {
        $this->authorize('vendor_invoice.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $approved = $this->approveVendorInvoiceService->approve($vendorInvoice, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    public function post(Request $request, VendorInvoice $vendorInvoice): JsonResponse
    {
        $this->authorize('vendor_invoice.post');

        $validated = $request->validate([
            'auto_approve' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $journal = $this->postVendorInvoiceService->post(
                invoice: $vendorInvoice,
                actor: $actor,
                autoApproveIfNeeded: (bool) ($validated['auto_approve'] ?? false),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $vendorInvoice->refresh();

        return response()->json([
            'data' => [
                'invoice' => $vendorInvoice,
                'journal' => $journal,
            ],
        ]);
    }
}
