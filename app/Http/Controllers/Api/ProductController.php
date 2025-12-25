<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\MasterData\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('product.view');

        $query = Product::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('product.view');

        return response()->json([
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('product.create');

        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'code' => ['required', 'string'],
            'name' => ['required', 'string'],
            'type' => ['required', 'string', Rule::in(['stock_item', 'service'])],
            'uom' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $product = $this->productService->create($validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('product.edit');

        $validated = $request->validate([
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'code' => ['sometimes', 'string'],
            'name' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string', Rule::in(['stock_item', 'service'])],
            'uom' => ['sometimes', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        try {
            $updated = $this->productService->update($product, $validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('product.delete');

        $product->delete();

        return response()->json(null, 204);
    }
}
