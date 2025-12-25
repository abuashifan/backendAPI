<?php

namespace App\Services\MasterData;

use App\Models\Customer;

class CustomerService
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function create(array $attributes): Customer
    {
        $companyId = (int) ($attributes['company_id'] ?? 0);
        $code = (string) ($attributes['code'] ?? '');

        if ($companyId <= 0 || $code === '') {
            throw new \DomainException('company_id and code are required.');
        }

        $exists = Customer::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw new \DomainException('Customer code already exists for this company.');
        }

        /** @var Customer $customer */
        $customer = Customer::query()->create($attributes);

        return $customer;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Customer $customer, array $attributes): Customer
    {
        $companyId = (int) ($attributes['company_id'] ?? $customer->company_id);
        $code = (string) ($attributes['code'] ?? $customer->code);

        $exists = Customer::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->whereKeyNot($customer->getKey())
            ->exists();

        if ($exists) {
            throw new \DomainException('Customer code already exists for this company.');
        }

        $customer->fill($attributes);
        $customer->save();

        return $customer;
    }
}
