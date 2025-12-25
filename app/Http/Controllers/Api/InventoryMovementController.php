<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    public function __construct(
        private readonly InventoryMovementService $inventoryMovementService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('inventory_movement.view');

        $query = InventoryMovement::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        $warehouseId = $request->query('warehouse_id');
        if ($warehouseId !== null) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        $type = $request->query('type');
        if (is_string($type) && in_array($type, ['in', 'out'], true)) {
            $query->where('type', $type);
        }

        return response()->json([
            'data' => $query->with(['lines'])->get(),
        ]);
    }

    public function show(InventoryMovement $inventoryMovement): JsonResponse
    {
        $this->authorize('inventory_movement.view');

        $inventoryMovement->loadMissing(['lines']);

        return response()->json([
            'data' => $inventoryMovement,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('inventory_movement.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'movement_number' => ['required', 'string'],
            'movement_date' => ['required', 'date'],
            'type' => ['required', 'in:in,out'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $movement = $this->inventoryMovementService->createDraft(
                movementAttributes: $validated,
                linesAttributes: $validated['lines'],
                actor: $actor,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $movement->loadMissing(['lines']);

        return response()->json([
            'data' => $movement,
        ], 201);
    }

    public function update(Request $request, InventoryMovement $inventoryMovement): JsonResponse
    {
        $this->authorize('inventory_movement.edit');

        $validated = $request->validate([
            'warehouse_id' => ['sometimes', 'integer', 'exists:warehouses,id'],
            'movement_number' => ['sometimes', 'string'],
            'movement_date' => ['sometimes', 'date'],
            'type' => ['sometimes', 'in:in,out'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string'],
        ]);

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $updated = $this->inventoryMovementService->updateDraft(
                movement: $inventoryMovement,
                movementAttributes: $validated,
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

    public function destroy(Request $request, InventoryMovement $inventoryMovement): JsonResponse
    {
        $this->authorize('inventory_movement.delete');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $this->inventoryMovementService->deleteDraft($inventoryMovement, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    /**
     * POST /inventory-movements/{inventoryMovement}/post
     */
    public function post(Request $request, InventoryMovement $inventoryMovement): JsonResponse
    {
        $this->authorize('inventory_movement.post');

        $actor = $request->user();
        if ($actor === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        try {
            $posted = $this->inventoryMovementService->post($inventoryMovement, $actor);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $posted->loadMissing(['lines']);

        return response()->json([
            'data' => $posted,
        ]);
    }
}
