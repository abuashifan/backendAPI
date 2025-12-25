<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\MasterData\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customerService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('customer.view');

        $query = Customer::query()->orderByDesc('id');

        $companyId = $request->query('company_id');
        if ($companyId !== null) {
            $query->where('company_id', (int) $companyId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('customer.view');

        return response()->json([
            'data' => $customer,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('customer.create');

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
            $customer = $this->customerService->create($validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $customer,
        ], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('customer.edit');

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
            $updated = $this->customerService->update($customer, $validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('customer.delete');

        $customer->delete();

        return response()->json(null, 204);
    }
}
