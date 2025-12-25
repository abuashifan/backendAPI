<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryMovementApiTest extends TestCase
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

    private function makeWarehouse(int $companyId): Warehouse
    {
        return Warehouse::query()->create([
            'company_id' => $companyId,
            'code' => 'WH-001',
            'name' => 'Main Warehouse',
            'address' => null,
            'is_active' => true,
        ]);
    }

    private function makeStockProduct(int $companyId, string $code = 'PRD-001'): Product
    {
        return Product::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => 'Item',
            'type' => 'stock_item',
            'uom' => 'pcs',
            'is_active' => true,
        ]);
    }

    private function makeServiceProduct(int $companyId, string $code = 'SRV-001'): Product
    {
        return Product::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => 'Service',
            'type' => 'service',
            'uom' => 'job',
            'is_active' => true,
        ]);
    }

    public function test_inventory_movement_requires_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $warehouse = $this->makeWarehouse($company->id);
        $product = $this->makeStockProduct($company->id);

        $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-001',
            'movement_date' => '2025-12-25',
            'type' => 'in',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 5],
            ],
        ])->assertForbidden();
    }

    public function test_can_create_post_and_prevent_negative_stock(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'inventory_movement.create',
            'inventory_movement.edit',
            'inventory_movement.view',
            'inventory_movement.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $warehouse = $this->makeWarehouse($company->id);
        $product = $this->makeStockProduct($company->id);

        // IN 10
        $createIn = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-IN-001',
            'movement_date' => '2025-12-25',
            'type' => 'in',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 1000],
            ],
        ])->assertCreated();

        $inId = (int) $createIn->json('data.id');

        $this->postJson("/api/inventory-movements/{$inId}/post")
            ->assertOk();

        // OUT 11 should fail (on hand is 10)
        $createOut = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-OUT-001',
            'movement_date' => '2025-12-25',
            'type' => 'out',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 11],
            ],
        ])->assertCreated();

        $outId = (int) $createOut->json('data.id');

        $this->postJson("/api/inventory-movements/{$outId}/post")
            ->assertStatus(409);

        // OUT 7 should succeed
        $this->putJson("/api/inventory-movements/{$outId}", [
            'lines' => [
                ['product_id' => $product->id, 'qty' => 7],
            ],
        ])->assertOk();

        $this->postJson("/api/inventory-movements/{$outId}/post")
            ->assertOk();
    }

    public function test_fifo_valuation_allocates_cost_layers_on_out(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'inventory_movement.create',
            'inventory_movement.edit',
            'inventory_movement.view',
            'inventory_movement.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $warehouse = $this->makeWarehouse($company->id);
        $product = $this->makeStockProduct($company->id);

        // IN 10 @ 1,000
        $in1 = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-IN-001',
            'movement_date' => '2025-12-25',
            'type' => 'in',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 10, 'unit_cost' => 1000],
            ],
        ])->assertCreated();

        $in1Id = (int) $in1->json('data.id');
        $this->postJson("/api/inventory-movements/{$in1Id}/post")->assertOk();

        // IN 5 @ 2,000
        $in2 = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-IN-002',
            'movement_date' => '2025-12-25',
            'type' => 'in',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => 2000],
            ],
        ])->assertCreated();

        $in2Id = (int) $in2->json('data.id');
        $this->postJson("/api/inventory-movements/{$in2Id}/post")->assertOk();

        // OUT 12 => cost = 10*1000 + 2*2000 = 14,000
        $out = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-OUT-001',
            'movement_date' => '2025-12-25',
            'type' => 'out',
            'lines' => [
                ['product_id' => $product->id, 'qty' => 12],
            ],
        ])->assertCreated();

        $outId = (int) $out->json('data.id');

        $posted = $this->postJson("/api/inventory-movements/{$outId}/post")
            ->assertOk();

        $this->assertSame('posted', $posted->json('data.status'));

        $lineTotal = (string) $posted->json('data.lines.0.valued_total_cost');
        $this->assertSame('14000.00', $lineTotal);
    }

    public function test_inventory_movement_rejects_service_products(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions(['inventory_movement.create']);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $warehouse = $this->makeWarehouse($company->id);
        $service = $this->makeServiceProduct($company->id);

        $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-001',
            'movement_date' => '2025-12-25',
            'type' => 'in',
            'lines' => [
                ['product_id' => $service->id, 'qty' => 1],
            ],
        ])->assertStatus(409);
    }
}
