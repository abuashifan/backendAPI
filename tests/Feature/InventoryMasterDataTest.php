<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryMasterDataTest extends TestCase
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

    public function test_cannot_create_product_without_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $this->postJson('/api/products', [
            'company_id' => $company->id,
            'code' => 'PRD-001',
            'name' => 'Product A',
            'type' => 'stock_item',
            'uom' => 'pcs',
        ])->assertForbidden();
    }

    public function test_can_create_and_update_product_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['product.create', 'product.edit', 'product.view']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $create = $this->postJson('/api/products', [
            'company_id' => $company->id,
            'code' => 'PRD-001',
            'name' => 'Product A',
            'type' => 'stock_item',
            'uom' => 'pcs',
        ]);

        $create->assertCreated();
        $productId = (int) $create->json('data.id');

        $update = $this->putJson("/api/products/{$productId}", [
            'name' => 'Product A Updated',
            'type' => 'service',
        ]);

        $update->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Product A Updated',
            'type' => 'service',
        ]);
    }

    public function test_product_code_must_be_unique_per_company(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['product.create']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        Product::query()->create([
            'company_id' => $company->id,
            'code' => 'PRD-001',
            'name' => 'Existing',
            'type' => 'stock_item',
            'uom' => 'pcs',
            'is_active' => true,
        ]);

        $this->postJson('/api/products', [
            'company_id' => $company->id,
            'code' => 'PRD-001',
            'name' => 'Duplicate',
            'type' => 'stock_item',
            'uom' => 'pcs',
        ])->assertStatus(409);
    }

    public function test_can_create_and_update_warehouse_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['warehouse.create', 'warehouse.edit', 'warehouse.view']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $create = $this->postJson('/api/warehouses', [
            'company_id' => $company->id,
            'code' => 'WH-001',
            'name' => 'Warehouse A',
        ]);

        $create->assertCreated();
        $warehouseId = (int) $create->json('data.id');

        $update = $this->putJson("/api/warehouses/{$warehouseId}", [
            'name' => 'Warehouse A Updated',
            'address' => 'Address',
        ]);

        $update->assertOk();

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouseId,
            'name' => 'Warehouse A Updated',
        ]);
    }

    public function test_warehouse_code_must_be_unique_per_company(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['warehouse.create']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        Warehouse::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-001',
            'name' => 'Existing',
            'address' => null,
            'is_active' => true,
        ]);

        $this->postJson('/api/warehouses', [
            'company_id' => $company->id,
            'code' => 'WH-001',
            'name' => 'Duplicate',
        ])->assertStatus(409);
    }
}
