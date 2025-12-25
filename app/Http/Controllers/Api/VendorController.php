<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\MasterData\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function __construct(
        private readonly VendorService $vendorService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('vendor.view');

        $query = Vendor::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        $this->authorize('vendor.view');

        return response()->json([
            'data' => $vendor,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('vendor.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'tax_id' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $vendor = $this->vendorService->create($validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $vendor,
        ], 201);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $this->authorize('vendor.edit');

        $validated = $request->validate([
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'code' => ['sometimes', 'string'],
            'name' => ['sometimes', 'string'],
            'tax_id' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->vendorService->update($vendor, $validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->authorize('vendor.delete');

        $vendor->delete();

        return response()->json(null, 204);
    }
}
