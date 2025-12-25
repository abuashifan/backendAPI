<?php

namespace App\Services\MasterData;

use App\Models\Vendor;

class VendorService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Vendor
    {
        $companyId = (int) ($attributes['company_id'] ?? 0);
        $code = (string) ($attributes['code'] ?? '');

        if ($companyId <= 0 || $code === '') {
            throw new \DomainException('company_id and code are required.');
        }

        $exists = Vendor::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new \DomainException('Vendor code already exists for this company.');
        }

        /** @var Vendor $vendor */
        $vendor = Vendor::query()->create($attributes);

        return $vendor;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Vendor $vendor, array $attributes): Vendor
    {
        $companyId = (int) ($attributes['company_id'] ?? $vendor->company_id);
        $code = (string) ($attributes['code'] ?? $vendor->code);

        $exists = Vendor::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereKeyNot($vendor->getKey())
            ->exists();

        if ($exists) {
            throw new \DomainException('Vendor code already exists for this company.');
        }

        $vendor->fill($attributes);
        $vendor->save();

        return $vendor;
    }
}
