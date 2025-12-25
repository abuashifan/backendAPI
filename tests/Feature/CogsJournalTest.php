<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CogsJournalTest extends TestCase
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

    private function makeOpenPeriod(Company $company): AccountingPeriod
    {
        return AccountingPeriod::query()->create([
            'company_id' => $company->id,
            'year' => 2025,
            'month' => 12,
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    /**
     * @param array<int, string> $codes
     */
    private function seedAccounts(Company $company, array $codes): void
    {
        foreach ($codes as $code) {
            ChartOfAccount::query()->create([
                'company_id' => $company->id,
                'code' => $code,
                'name' => 'COA ' . $code,
                'type' => str_starts_with($code, '1-') ? 'asset' : (str_starts_with($code, '2-') ? 'liability' : (str_starts_with($code, '4-') ? 'income' : 'expense')),
                'normal_balance' => str_starts_with($code, '1-') ? 'debit' : 'credit',
                'parent_id' => null,
                'level' => 2,
                'is_postable' => true,
                'is_active' => true,
            ]);
        }
    }

    private function makeCustomer(Company $company): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'code' => 'C001',
            'name' => 'Customer A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
    }

    private function makeWarehouse(Company $company): Warehouse
    {
        return Warehouse::query()->create([
            'company_id' => $company->id,
            'code' => 'WH-001',
            'name' => 'Main',
            'address' => null,
            'is_active' => true,
        ]);
    }

    private function makeStockProduct(Company $company): Product
    {
        return Product::query()->create([
            'company_id' => $company->id,
            'code' => 'PRD-001',
            'name' => 'Item',
            'type' => 'stock_item',
            'uom' => 'pcs',
            'is_active' => true,
        ]);
    }

    private function assertJournalLine(Journal $journal, string $accountCode, string $debit, string $credit): void
    {
        $journal->loadMissing(['lines.account']);

        $line = $journal->lines->first(fn ($l) => $l->account !== null && $l->account->code === $accountCode);
        $this->assertNotNull($line, 'Missing journal line for account ' . $accountCode);

        $this->assertSame($debit, (string) $line->debit);
        $this->assertSame($credit, (string) $line->credit);
    }

    public function test_posting_sales_invoice_creates_cogs_journal_when_inventory_out_exists(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'sales_invoice.approve',
            'sales_invoice.post',
            'inventory_movement.create',
            'inventory_movement.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100', '1-1400', '5-1100']);

        $customer = $this->makeCustomer($company);
        $warehouse = $this->makeWarehouse($company);
        $product = $this->makeStockProduct($company);

        // Seed FIFO layers: IN 10 @ 1,000 and IN 5 @ 2,000
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
        $this->postJson('/api/inventory-movements/' . (int) $in1->json('data.id') . '/post')->assertOk();

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
        $this->postJson('/api/inventory-movements/' . (int) $in2->json('data.id') . '/post')->assertOk();

        // Create sales invoice (qty 12)
        $si = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-001',
            'invoice_date' => '2025-12-25',
            'due_date' => '2026-01-24',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 0,
            'lines' => [
                ['product_id' => $product->id, 'description' => 'Item', 'qty' => 12, 'unit_price' => 10000],
            ],
        ])->assertCreated();

        $salesInvoiceId = (int) $si->json('data.id');

        // Post inventory OUT linked to invoice (valued_total_cost should be 14,000)
        $out = $this->postJson('/api/inventory-movements', [
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'movement_number' => 'IM-OUT-001',
            'movement_date' => '2025-12-25',
            'type' => 'out',
            'reference_type' => 'ar.sales_invoice',
            'reference_id' => $salesInvoiceId,
            'lines' => [
                ['product_id' => $product->id, 'qty' => 12],
            ],
        ])->assertCreated();

        $outId = (int) $out->json('data.id');
        $this->postJson("/api/inventory-movements/{$outId}/post")
            ->assertOk()
            ->assertJsonPath('data.lines.0.valued_total_cost', '14000.00');

        // Approve + post invoice
        $this->postJson("/api/sales-invoices/{$salesInvoiceId}/approve")->assertOk();

        $this->postJson("/api/sales-invoices/{$salesInvoiceId}/post", ['auto_approve' => false])
            ->assertOk();

        $cogsJournal = Journal::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'inventory.cogs')
            ->where('source_id', $salesInvoiceId)
            ->first();

        $this->assertNotNull($cogsJournal);
        $this->assertSame('posted', $cogsJournal->status);

        $this->assertJournalLine($cogsJournal, '5-1100', '14000.00', '0.00');
        $this->assertJournalLine($cogsJournal, '1-1400', '0.00', '14000.00');
    }
}
