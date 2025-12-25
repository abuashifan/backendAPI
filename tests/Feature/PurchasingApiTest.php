<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchasingApiTest extends TestCase
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
                'type' => str_starts_with($code, '1-') ? 'asset' : (str_starts_with($code, '2-') ? 'liability' : 'income'),
                'normal_balance' => str_starts_with($code, '1-') ? 'debit' : 'credit',
                'parent_id' => null,
                'level' => 2,
                'is_postable' => true,
                'is_active' => true,
            ]);
        }
    }

    public function test_purchase_order_requires_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'code' => 'V001',
            'name' => 'Vendor A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $this->postJson('/api/purchase-orders', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-001',
            'po_date' => '2025-12-10',
            'currency_code' => 'IDR',
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 100],
            ],
        ])->assertForbidden();
    }

    public function test_can_create_approve_and_cancel_purchase_order_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'purchase_order.create',
            'purchase_order.view',
            'purchase_order.edit',
            'purchase_order.delete',
            'purchase_order.approve',
            'purchase_order.cancel',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();

        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'code' => 'V001',
            'name' => 'Vendor A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/purchase-orders', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'po_number' => 'PO-001',
            'po_date' => '2025-12-10',
            'currency_code' => 'IDR',
            'tax_amount' => 0,
            'lines' => [
                ['description' => 'Item', 'qty' => 2, 'unit_price' => 100],
            ],
        ]);

        $create->assertCreated();
        $poId = (int) $create->json('data.id');

        $approve = $this->postJson("/api/purchase-orders/{$poId}/approve");
        $approve->assertOk();

        $po = PurchaseOrder::query()->findOrFail($poId);
        $this->assertSame('approved', $po->status);

        $cancel = $this->postJson("/api/purchase-orders/{$poId}/cancel");
        $cancel->assertOk();

        $po->refresh();
        $this->assertSame('cancelled', $po->status);
    }

    public function test_can_create_approve_and_post_vendor_invoice_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'vendor_invoice.create',
            'vendor_invoice.view',
            'vendor_invoice.edit',
            'vendor_invoice.delete',
            'vendor_invoice.approve',
            'vendor_invoice.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1400', '2-1100']);

        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'code' => 'V001',
            'name' => 'Vendor A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $create = $this->postJson('/api/vendor-invoices', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 0,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 1000],
            ],
        ]);

        $create->assertCreated();
        $invoiceId = (int) $create->json('data.id');

        $this->postJson("/api/vendor-invoices/{$invoiceId}/approve")
            ->assertOk();

        $post = $this->postJson("/api/vendor-invoices/{$invoiceId}/post", ['auto_approve' => false]);
        $post->assertOk();

        $this->assertNotNull($post->json('data.journal.id'));
        $this->assertSame('ap.vendor_invoice', $post->json('data.journal.source_type'));

        $this->assertNotNull($post->json('data.invoice.posted_at'));
    }

    public function test_can_create_approve_and_post_vendor_payment_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'vendor_invoice.create',
            'vendor_invoice.approve',
            'vendor_invoice.post',
            'vendor_payment.create',
            'vendor_payment.view',
            'vendor_payment.edit',
            'vendor_payment.delete',
            'vendor_payment.approve',
            'vendor_payment.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1400', '2-1100', '1-1100']);

        $vendor = Vendor::query()->create([
            'company_id' => $company->id,
            'code' => 'V001',
            'name' => 'Vendor A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoiceCreate = $this->postJson('/api/vendor-invoices', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-002',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 0,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 500],
            ],
        ]);
        $invoiceCreate->assertCreated();
        $invoiceId = (int) $invoiceCreate->json('data.id');

        $this->postJson("/api/vendor-invoices/{$invoiceId}/approve")->assertOk();
        $this->postJson("/api/vendor-invoices/{$invoiceId}/post")->assertOk();

        $paymentCreate = $this->postJson('/api/vendor-payments', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'PAY-001',
            'payment_date' => '2025-12-15',
            'payment_method' => 'cash',
            'amount' => 500,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'allocations' => [
                ['vendor_invoice_id' => $invoiceId, 'allocated_amount' => 500],
            ],
        ]);

        $paymentCreate->assertCreated();
        $paymentId = (int) $paymentCreate->json('data.id');

        $this->postJson("/api/vendor-payments/{$paymentId}/approve")
            ->assertOk();

        $post = $this->postJson("/api/vendor-payments/{$paymentId}/post", ['auto_approve' => false]);
        $post->assertOk();

        $this->assertNotNull($post->json('data.journal.id'));
        $this->assertSame('ap.vendor_payment', $post->json('data.journal.source_type'));
        $this->assertNotNull($post->json('data.payment.posted_at'));
    }
}
