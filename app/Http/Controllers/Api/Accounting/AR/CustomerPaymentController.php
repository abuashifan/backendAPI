<?php

namespace App\Http\Controllers\Api\Accounting\AR;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Services\Accounting\Payments\ApproveCustomerPaymentService;
use App\Services\Accounting\Payments\CustomerPaymentService;
use App\Services\Accounting\Payments\PostCustomerPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPaymentController extends Controller
{
    public function __construct(
        private readonly CustomerPaymentService $customerPaymentService,
        private readonly ApproveCustomerPaymentService $approveCustomerPaymentService,
        private readonly PostCustomerPaymentService $postCustomerPaymentService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('customer_payment.view');

        $query = CustomerPayment::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        $customerId = $request->query('customer_id');
        if ($customerId !== null) {
            $query->where('customer_id', (int) $customerId);
        }

        return response()->json([
            'data' => $query->with(['allocations'])->get(),
        ]);
    }

    public function show(CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('customer_payment.view');

        $customerPayment->loadMissing(['allocations']);

        return response()->json([
            'data' => $customerPayment,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('customer_payment.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'receipt_number' => ['required', 'string'],
            'receipt_date' => ['required', 'date'],
            'receipt_method' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $payment = $this->customerPaymentService->createDraft(
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

    public function update(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('customer_payment.edit');

        $validated = $request->validate([
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
            'receipt_number' => ['sometimes', 'string'],
            'receipt_date' => ['sometimes', 'date'],
            'receipt_method' => ['sometimes', 'string'],
            'amount' => ['sometimes', 'numeric'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'allocations.*.allocated_amount' => ['required', 'numeric'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $updated = $this->customerPaymentService->updateDraft(
                payment: $customerPayment,
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

    public function destroy(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('customer_payment.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->customerPaymentService->deleteDraft($customerPayment, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function approve(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('customer_payment.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $approved = $this->approveCustomerPaymentService->approve($customerPayment, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    public function post(Request $request, CustomerPayment $customerPayment): JsonResponse
    {
        $this->authorize('customer_payment.post');

        $validated = $request->validate([
            'auto_approve' => ['nullable', 'boolean'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $journal = $this->postCustomerPaymentService->post(
                payment: $customerPayment,
                actor: $actor,
                autoApproveIfNeeded: (bool) ($validated['auto_approve'] ?? false),
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $customerPayment->refresh();

        return response()->json([
            'data' => [
                'payment' => $customerPayment,
                'journal' => $journal,
            ],
        ]);
    }
}
