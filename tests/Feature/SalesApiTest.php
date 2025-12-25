<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesApiTest extends TestCase
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

    public function test_sales_invoice_requires_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $customer = $this->makeCustomer($company);

        $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 0,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 100],
            ],
        ])->assertForbidden();
    }

    public function test_can_create_approve_and_post_sales_invoice_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'sales_invoice.view',
            'sales_invoice.edit',
            'sales_invoice.delete',
            'sales_invoice.approve',
            'sales_invoice.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100', '2-1200']);

        $customer = $this->makeCustomer($company);

        $create = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 100,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 1000],
            ],
        ]);

        $create->assertCreated();
        $invoiceId = (int) $create->json('data.id');

        $this->postJson("/api/sales-invoices/{$invoiceId}/approve")
            ->assertOk();

        $post = $this->postJson("/api/sales-invoices/{$invoiceId}/post", ['auto_approve' => false]);
        $post->assertOk();

        $this->assertNotNull($post->json('data.journal.id'));
        $this->assertSame('ar.sales_invoice', $post->json('data.journal.source_type'));
        $this->assertNotNull($post->json('data.invoice.posted_at'));

        $invoice = SalesInvoice::query()->findOrFail($invoiceId);
        $this->assertNotNull($invoice->posted_at);
    }

    public function test_can_create_approve_and_post_customer_payment_with_permission(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'sales_invoice.approve',
            'sales_invoice.post',
            'customer_payment.create',
            'customer_payment.view',
            'customer_payment.edit',
            'customer_payment.delete',
            'customer_payment.approve',
            'customer_payment.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100', '2-1200', '1-1100']);

        $customer = $this->makeCustomer($company);

        // Create + approve + post sales invoice (required by allocation rules)
        $invoiceCreate = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-002',
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

        $this->postJson("/api/sales-invoices/{$invoiceId}/approve")->assertOk();
        $this->postJson("/api/sales-invoices/{$invoiceId}/post")->assertOk();

        $paymentCreate = $this->postJson('/api/customer-payments', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-001',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
            'amount' => 500,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'allocations' => [
                ['sales_invoice_id' => $invoiceId, 'allocated_amount' => 500],
            ],
        ]);

        $paymentCreate->assertCreated();
        $paymentId = (int) $paymentCreate->json('data.id');

        $this->postJson("/api/customer-payments/{$paymentId}/approve")
            ->assertOk();

        $post = $this->postJson("/api/customer-payments/{$paymentId}/post", ['auto_approve' => false]);
        $post->assertOk();

        $this->assertNotNull($post->json('data.journal.id'));
        $this->assertSame('ar.customer_payment', $post->json('data.journal.source_type'));
        $this->assertNotNull($post->json('data.payment.posted_at'));

        $payment = CustomerPayment::query()->findOrFail($paymentId);
        $this->assertNotNull($payment->posted_at);
    }
}
