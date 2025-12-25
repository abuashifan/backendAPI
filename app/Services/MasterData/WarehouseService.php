<?php

namespace App\Services\MasterData;

use App\Models\Warehouse;

class WarehouseService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Warehouse
    {
        $companyId = (int) ($attributes['company_id'] ?? 0);
        $code = (string) ($attributes['code'] ?? '');

        if ($companyId <= 0 || $code === '') {
            throw new \DomainException('company_id and code are required.');
        }

        $exists = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new \DomainException('Warehouse code already exists for this company.');
        }

        /** @var Warehouse $warehouse */
        $warehouse = Warehouse::query()->create($attributes);

        return $warehouse;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Warehouse $warehouse, array $attributes): Warehouse
    {
        $companyId = (int) ($attributes['company_id'] ?? $warehouse->company_id);
        $code = (string) ($attributes['code'] ?? $warehouse->code);

        $exists = Warehouse::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereKeyNot($warehouse->getKey())
            ->exists();

        if ($exists) {
            throw new \DomainException('Warehouse code already exists for this company.');
        }

        $warehouse->fill($attributes);
        $warehouse->save();

        return $warehouse;
    }
}
