<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Services\MasterData\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('warehouse.view');

        $query = Warehouse::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouse.view');

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('warehouse.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $warehouse = $this->warehouseService->create($validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $warehouse,
        ], 201);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouse.edit');

        $validated = $request->validate([
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'code' => ['sometimes', 'string'],
            'name' => ['sometimes', 'string'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->warehouseService->update($warehouse, $validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('warehouse.delete');

        $warehouse->delete();

        return response()->json(null, 204);
    }
}
