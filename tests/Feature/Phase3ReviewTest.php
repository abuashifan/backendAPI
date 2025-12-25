<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Phase3ReviewTest extends TestCase
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

    private function makeVendor(Company $company): Vendor
    {
        return Vendor::query()->create([
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

    private function assertJournalLine(Journal $journal, string $accountCode, string $debit, string $credit): void
    {
        $journal->loadMissing(['lines.account']);

        $line = $journal->lines->first(fn ($l) => $l->account !== null && $l->account->code === $accountCode);
        $this->assertNotNull($line, 'Missing journal line for account ' . $accountCode);

        $this->assertSame($debit, (string) $line->debit);
        $this->assertSame($credit, (string) $line->credit);
    }

    public function test_phase3_ap_end_to_end_creates_expected_posted_journals(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'vendor_invoice.create',
            'vendor_invoice.approve',
            'vendor_invoice.post',
            'vendor_payment.create',
            'vendor_payment.approve',
            'vendor_payment.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1400', '2-1100', '1-1100']);

        $vendor = $this->makeVendor($company);

        $invoiceCreate = $this->postJson('/api/vendor-invoices', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-AP-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 0,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 1000],
            ],
        ]);
        $invoiceCreate->assertCreated();
        $invoiceId = (int) $invoiceCreate->json('data.id');

        $this->postJson("/api/vendor-invoices/{$invoiceId}/approve")
            ->assertOk();

        $invoicePost = $this->postJson("/api/vendor-invoices/{$invoiceId}/post", ['auto_approve' => false]);
        $invoicePost->assertOk();
        $invoiceJournalId = (int) $invoicePost->json('data.journal.id');

        $invoiceJournal = Journal::query()->with(['lines.account'])->findOrFail($invoiceJournalId);
        $this->assertSame('posted', $invoiceJournal->status);
        $this->assertSame('ap.vendor_invoice', $invoiceJournal->source_type);

        $this->assertJournalLine($invoiceJournal, '1-1400', '1000.00', '0.00');
        $this->assertJournalLine($invoiceJournal, '2-1100', '0.00', '1000.00');

        $paymentCreate = $this->postJson('/api/vendor-payments', [
            'company_id' => $company->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'PAY-AP-001',
            'payment_date' => '2025-12-15',
            'payment_method' => 'cash',
            'amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'allocations' => [
                ['vendor_invoice_id' => $invoiceId, 'allocated_amount' => 1000],
            ],
        ]);
        $paymentCreate->assertCreated();
        $paymentId = (int) $paymentCreate->json('data.id');

        $this->postJson("/api/vendor-payments/{$paymentId}/approve")
            ->assertOk();

        $paymentPost = $this->postJson("/api/vendor-payments/{$paymentId}/post", ['auto_approve' => false]);
        $paymentPost->assertOk();
        $paymentJournalId = (int) $paymentPost->json('data.journal.id');

        $paymentJournal = Journal::query()->with(['lines.account'])->findOrFail($paymentJournalId);
        $this->assertSame('posted', $paymentJournal->status);
        $this->assertSame('ap.vendor_payment', $paymentJournal->source_type);

        $this->assertJournalLine($paymentJournal, '2-1100', '1000.00', '0.00');
        $this->assertJournalLine($paymentJournal, '1-1100', '0.00', '1000.00');
    }

    public function test_phase3_ar_end_to_end_creates_expected_posted_journals(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'sales_invoice.approve',
            'sales_invoice.post',
            'customer_payment.create',
            'customer_payment.approve',
            'customer_payment.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100', '2-1200', '1-1100']);

        $customer = $this->makeCustomer($company);

        $invoiceCreate = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'tax_amount' => 100,
            'lines' => [
                ['description' => 'Item', 'qty' => 1, 'unit_price' => 1000],
            ],
        ]);
        $invoiceCreate->assertCreated();
        $invoiceId = (int) $invoiceCreate->json('data.id');

        $this->postJson("/api/sales-invoices/{$invoiceId}/approve")
            ->assertOk();

        $invoicePost = $this->postJson("/api/sales-invoices/{$invoiceId}/post", ['auto_approve' => false]);
        $invoicePost->assertOk();
        $invoiceJournalId = (int) $invoicePost->json('data.journal.id');

        $invoiceJournal = Journal::query()->with(['lines.account'])->findOrFail($invoiceJournalId);
        $this->assertSame('posted', $invoiceJournal->status);
        $this->assertSame('ar.sales_invoice', $invoiceJournal->source_type);

        $this->assertJournalLine($invoiceJournal, '1-1300', '1100.00', '0.00');
        $this->assertJournalLine($invoiceJournal, '4-1100', '0.00', '1000.00');
        $this->assertJournalLine($invoiceJournal, '2-1200', '0.00', '100.00');

        $paymentCreate = $this->postJson('/api/customer-payments', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-AR-001',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
            'amount' => 1100,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'allocations' => [
                ['sales_invoice_id' => $invoiceId, 'allocated_amount' => 1100],
            ],
        ]);
        $paymentCreate->assertCreated();
        $paymentId = (int) $paymentCreate->json('data.id');

        $this->postJson("/api/customer-payments/{$paymentId}/approve")
            ->assertOk();

        $paymentPost = $this->postJson("/api/customer-payments/{$paymentId}/post", ['auto_approve' => false]);
        $paymentPost->assertOk();
        $paymentJournalId = (int) $paymentPost->json('data.journal.id');

        $paymentJournal = Journal::query()->with(['lines.account'])->findOrFail($paymentJournalId);
        $this->assertSame('posted', $paymentJournal->status);
        $this->assertSame('ar.customer_payment', $paymentJournal->source_type);

        $this->assertJournalLine($paymentJournal, '1-1100', '1100.00', '0.00');
        $this->assertJournalLine($paymentJournal, '1-1300', '0.00', '1100.00');
    }

    public function test_phase3_closed_period_blocks_posting_sales_invoice(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'sales_invoice.approve',
            'sales_invoice.post',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $period = $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100']);

        $customer = $this->makeCustomer($company);

        $invoiceCreate = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-002',
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

        $this->postJson("/api/sales-invoices/{$invoiceId}/approve")
            ->assertOk();

        $period->status = 'closed';
        $period->closed_at = now();
        $period->save();

        $this->postJson("/api/sales-invoices/{$invoiceId}/post")
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Accounting period is closed.',
            ]);
    }

    public function test_phase3_payment_allocation_requires_posted_invoice(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create();
        $user->syncPermissions([
            'sales_invoice.create',
            'customer_payment.create',
        ]);
        Sanctum::actingAs($user);

        $company = $this->makeCompany();
        $this->makeOpenPeriod($company);
        $this->seedAccounts($company, ['1-1300', '4-1100', '1-1100']);

        $customer = $this->makeCustomer($company);

        $invoiceCreate = $this->postJson('/api/sales-invoices', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-AR-003',
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

        $this->postJson('/api/customer-payments', [
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-AR-002',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
            'amount' => 500,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'allocations' => [
                ['sales_invoice_id' => $invoiceId, 'allocated_amount' => 500],
            ],
        ])
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Sales invoice must be posted before allocating payment.',
            ]);
    }
}
