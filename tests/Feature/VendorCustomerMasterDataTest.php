<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorCustomerMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    private function makeCompany(): Company
    {
        return Company::query()->create([
            'code' => 'CMP001',
            'name' => 'Test Company',
            'base_currency' => 'IDR',
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    public function test_can_create_and_update_vendor_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['vendor.create', 'vendor.edit', 'vendor.view']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $create = $this->postJson('/api/vendors', [
            'company_id' => $company->id,
            'code' => 'VND-001',
            'name' => 'Vendor A',
            'email' => 'vendor@example.com',
        ]);

        $create->assertCreated();
        $vendorId = (int) $create->json('data.id');

        $update = $this->putJson("/api/vendors/{$vendorId}", [
            'name' => 'Vendor A Updated',
        ]);

        $update->assertOk();

        $this->assertDatabaseHas('vendors', [
            'id' => $vendorId,
            'name' => 'Vendor A Updated',
        ]);
    }

    public function test_vendor_code_must_be_unique_per_company(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['vendor.create']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        Vendor::query()->create([
            'company_id' => $company->id,
            'code' => 'VND-001',
            'name' => 'Existing',
            'is_active' => true,
        ]);

        $this->postJson('/api/vendors', [
            'company_id' => $company->id,
            'code' => 'VND-001',
            'name' => 'Duplicate',
        ])->assertStatus(409);
    }

    public function test_cannot_create_customer_without_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $this->postJson('/api/customers', [
            'company_id' => $company->id,
            'code' => 'CST-001',
            'name' => 'Customer A',
        ])->assertForbidden();
    }

    public function test_can_create_customer_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['customer.create', 'customer.view']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $create = $this->postJson('/api/customers', [
            'company_id' => $company->id,
            'code' => 'CST-001',
            'name' => 'Customer A',
        ]);

        $create->assertCreated();
        $customerId = (int) $create->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'code' => 'CST-001',
        ]);

        $show = $this->getJson("/api/customers/{$customerId}");
        $show->assertOk();
    }
}
