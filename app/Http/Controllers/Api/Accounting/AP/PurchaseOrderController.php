<?php

namespace App\Http\Controllers\Api\Accounting\AP;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\Accounting\AP\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('purchase_order.view');

        $query = PurchaseOrder::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json([
            'data' => $query->with(['lines'])->get(),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_order.view');

        $purchaseOrder->loadMissing(['lines']);

        return response()->json([
            'data' => $purchaseOrder,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('purchase_order.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'po_number' => ['required', 'string'],
            'po_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency_code' => ['required', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
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
            $po = $this->purchaseOrderService->createDraft(
                poAttributes: $validated,
                linesAttributes: $validated['lines'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $po->loadMissing(['lines']);

        return response()->json([
            'data' => $po,
        ], 201);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_order.edit');

        $validated = $request->validate([
            'vendor_id' => ['sometimes', 'integer', 'exists:vendors,id'],
            'po_number' => ['sometimes', 'string'],
            'po_date' => ['sometimes', 'date'],
            'expected_date' => ['nullable', 'date'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
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
            $updated = $this->purchaseOrderService->updateDraft(
                po: $purchaseOrder,
                poAttributes: $validated,
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

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_order.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->purchaseOrderService->deleteDraft($purchaseOrder, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_order.approve');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $approved = $this->purchaseOrderService->approve($purchaseOrder, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $approved,
        ]);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase_order.cancel');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $cancelled = $this->purchaseOrderService->cancel($purchaseOrder, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'data' => $cancelled,
        ]);
    }
}
