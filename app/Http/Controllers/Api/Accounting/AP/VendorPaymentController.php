<?php

namespace App\Http\Controllers\Api\Accounting\AP;

use App\Http\Controllers\Controller;
use App\Models\VendorPayment;
use App\Services\Accounting\Payments\ApproveVendorPaymentService;
use App\Services\Accounting\Payments\PostVendorPaymentService;
use App\Services\Accounting\Payments\VendorPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function __construct(
        private readonly VendorPaymentService $vendorPaymentService,
        private readonly ApproveVendorPaymentService $approveVendorPaymentService,
        private readonly PostVendorPaymentService $postVendorPaymentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('vendor_payment.view');

        $query = VendorPayment::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        $vendorId = $request->query('vendor_id');
        if ($vendorId !== null) {
            $query->where('vendor_id', (int) $vendorId);
        }

        return response()->json([
            'data' => $query->with(['allocations'])->get(),
        ]);
    }

    public function show(VendorPayment $vendorPayment): JsonResponse
    {
        $this->authorize('vendor_payment.view');

        $vendorPayment->loadMissing(['allocations']);

        return response()->json([
            'data' => $vendorPayment,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('vendor_payment.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'payment_number' => ['required', 'string'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.vendor_invoice_id' => ['required', 'integer', 'exists:vendor_invoices,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $payment = $this->vendorPaymentService->createDraft(
                paymentAttributes: $validated,
                allocationsAttributes: $validated['allocations'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $payment->loadMissing(['allocations']);

        return response()->json([
            'data' => $payment,
        ], 201);
    }

    public function update(Request $request, VendorPayment $vendorPayment): JsonResponse
    {
        $this->authorize('vendor_payment.edit');

        $validated = $request->validate([
            'vendor_id' => ['sometimes', 'integer', 'exists:vendors,id'],
            'payment_number' => ['sometimes', 'string'],
            'payment_date' => ['sometimes', 'date'],
            'payment_method' => ['sometimes', 'string'],
            'amount' => ['sometimes', 'numeric'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.vendor_invoice_id' => ['required', 'integer', 'exists:vendor_invoices,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $updated = $this->vendorPaymentService->updateDraft(
                payment: $vendorPayment,
                paymentAttributes: $validated,
                allocationsAttributes: $validated['allocations'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $updated->loadMissing(['allocations']);

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, VendorPayment $vendorPayment): JsonResponse
    {
        $this->authorize('vendor_payment.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->vendorPaymentService->deleteDraft($vendorPayment, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function approve(Request $request, VendorPayment $vendorPayment): JsonResponse
    {
        $this->authorize('vendor_payment.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $approved = $this->approveVendorPaymentService->approve($vendorPayment, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    public function post(Request $request, VendorPayment $vendorPayment): JsonResponse
    {
        $this->authorize('vendor_payment.post');

        $validated = $request->validate([
            'auto_approve' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $journal = $this->postVendorPaymentService->post(
                payment: $vendorPayment,
                actor: $actor,
                autoApproveIfNeeded: (bool) ($validated['auto_approve'] ?? false),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $vendorPayment->refresh();

        return response()->json([
            'data' => [
                'payment' => $vendorPayment,
                'journal' => $journal,
            ],
        ]);
    }
}
