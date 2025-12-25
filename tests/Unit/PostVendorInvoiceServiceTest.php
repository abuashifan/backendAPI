<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\Accounting\AP\PostVendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostVendorInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company:Company, period:AccountingPeriod, inventory:ChartOfAccount, ap:ChartOfAccount}
     */
    private function makeCompanyPeriodAndAccounts(): array
    {
        $company = Company::query()->create([
            'code' => 'CMP001',
            'name' => 'Test Company',
            'base_currency' => 'IDR',
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $period = AccountingPeriod::query()->create([
            'company_id' => $company->id,
            'year' => 2025,
            'month' => 12,
            'start_date' => '2025-12-01',
            'end_date' => '2025-12-31',
            'status' => 'open',
            'closed_at' => null,
        ]);

        $inventory = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '1-1400',
            'name' => 'Inventory',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => null,
            'level' => 2,
            'is_postable' => true,
            'is_active' => true,
        ]);

        $ap = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '2-1100',
            'name' => 'Accounts Payable',
            'type' => 'liability',
            'normal_balance' => 'credit',
            'parent_id' => null,
            'level' => 2,
            'is_postable' => true,
            'is_active' => true,
        ]);

        return compact('company', 'period', 'inventory', 'ap');
    }

    public function test_can_post_vendor_invoice_creates_posted_journal_and_marks_invoice_posted(): void
    {
        $ctx = $this->makeCompanyPeriodAndAccounts();

        $actor = User::factory()->create();

        $vendor = Vendor::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'V001',
            'name' => 'Vendor A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-001',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'approved',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        /** @var PostVendorInvoiceService $service */
        $service = app(PostVendorInvoiceService::class);

        $postedJournal = $service->post($invoice, $actor);

        $this->assertInstanceOf(Journal::class, $postedJournal);
        $this->assertSame('posted', $postedJournal->status);
        $this->assertSame('ap.vendor_invoice', $postedJournal->source_type);
        $this->assertSame($invoice->id, $postedJournal->source_id);

        $invoice->refresh();
        $this->assertNotNull($invoice->posted_at);
        $this->assertSame($actor->id, $invoice->posted_by);

        $this->assertDatabaseHas('journals', [
            'id' => $postedJournal->id,
            'company_id' => $ctx['company']->id,
            'period_id' => $ctx['period']->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseCount('journal_lines', 2);
    }

    public function test_post_blocks_when_period_is_closed(): void
    {
        $ctx = $this->makeCompanyPeriodAndAccounts();
        $ctx['period']->update(['status' => 'closed', 'closed_at' => now()]);

        $actor = User::factory()->create();

        $vendor = Vendor::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'V002',
            'name' => 'Vendor B',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-002',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'approved',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        /** @var PostVendorInvoiceService $service */
        $service = app(PostVendorInvoiceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Accounting period is closed.');

        $service->post($invoice, $actor);
    }

    public function test_can_auto_approve_and_post_from_draft_when_enabled(): void
    {
        $ctx = $this->makeCompanyPeriodAndAccounts();

        $actor = User::factory()->create();

        $vendor = Vendor::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'V003',
            'name' => 'Vendor C',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'purchase_order_id' => null,
            'invoice_number' => 'INV-003',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'draft',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $actor->id,
            'approved_by' => null,
            'approved_at' => null,
            'source_type' => null,
            'source_id' => null,
        ]);

        /** @var PostVendorInvoiceService $service */
        $service = app(PostVendorInvoiceService::class);

        $postedJournal = $service->post($invoice, $actor, true);

        $this->assertSame('posted', $postedJournal->status);

        $invoice->refresh();
        $this->assertSame('approved', $invoice->status);
        $this->assertNotNull($invoice->approved_at);
        $this->assertNotNull($invoice->posted_at);
    }
}
