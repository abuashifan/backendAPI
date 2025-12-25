<?php

namespace App\Services\MasterData;

use App\Models\Product;

class ProductService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Product
    {
        $companyId = (int) ($attributes['company_id'] ?? 0);
        $code = (string) ($attributes['code'] ?? '');

        if ($companyId <= 0 || $code === '') {
            throw new \DomainException('company_id and code are required.');
        }

        $exists = Product::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new \DomainException('Product code already exists for this company.');
        }

        /** @var Product $product */
        $product = Product::query()->create($attributes);

        return $product;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Product $product, array $attributes): Product
    {
        $companyId = (int) ($attributes['company_id'] ?? $product->company_id);
        $code = (string) ($attributes['code'] ?? $product->code);

        $exists = Product::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereKeyNot($product->getKey())
            ->exists();

        if ($exists) {
            throw new \DomainException('Product code already exists for this company.');
        }

        $product->fill($attributes);
        $product->save();

        return $product;
    }
}
