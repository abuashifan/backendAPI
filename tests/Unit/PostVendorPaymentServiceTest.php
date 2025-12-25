<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Journal;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Services\Accounting\Payments\PostVendorPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostVendorPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company:Company, period:AccountingPeriod, cash:ChartOfAccount, ap:ChartOfAccount}
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

        $cash = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '1-1100',
            'name' => 'Cash',
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

        return compact('company', 'period', 'cash', 'ap');
    }

    public function test_can_post_vendor_payment_creates_posted_journal_and_marks_payment_posted(): void
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
            'invoice_number' => 'INV-PAID',
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
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        $payment = VendorPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'PAY-001',
            'payment_date' => '2025-12-15',
            'payment_method' => 'cash',
            'amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'approved',
            'notes' => null,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'posted_by' => null,
            'posted_at' => null,
            'source_type' => null,
            'source_id' => null,
        ]);

        VendorPaymentAllocation::query()->create([
            'vendor_payment_id' => $payment->id,
            'vendor_invoice_id' => $invoice->id,
            'allocated_amount' => 1000,
        ]);

        /** @var PostVendorPaymentService $service */
        $service = app(PostVendorPaymentService::class);

        $postedJournal = $service->post($payment, $actor);

        $this->assertInstanceOf(Journal::class, $postedJournal);
        $this->assertSame('posted', $postedJournal->status);
        $this->assertSame('ap.vendor_payment', $postedJournal->source_type);
        $this->assertSame($payment->id, $postedJournal->source_id);

        $payment->refresh();
        $this->assertNotNull($payment->posted_at);
        $this->assertSame($actor->id, $payment->posted_by);

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
            'invoice_number' => 'INV-PAID-2',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'approved',
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        $payment = VendorPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'PAY-002',
            'payment_date' => '2025-12-15',
            'payment_method' => 'cash',
            'amount' => 500,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'approved',
            'notes' => null,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'posted_by' => null,
            'posted_at' => null,
            'source_type' => null,
            'source_id' => null,
        ]);

        VendorPaymentAllocation::query()->create([
            'vendor_payment_id' => $payment->id,
            'vendor_invoice_id' => $invoice->id,
            'allocated_amount' => 500,
        ]);

        /** @var PostVendorPaymentService $service */
        $service = app(PostVendorPaymentService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Accounting period is closed.');

        $service->post($payment, $actor);
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
            'invoice_number' => 'INV-PAID-3',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'approved',
            'subtotal' => 750,
            'tax_amount' => 0,
            'total_amount' => 750,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        $payment = VendorPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $vendor->id,
            'payment_number' => 'PAY-003',
            'payment_date' => '2025-12-15',
            'payment_method' => 'cash',
            'amount' => 750,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'status' => 'draft',
            'notes' => null,
            'created_by' => $actor->id,
            'approved_by' => null,
            'approved_at' => null,
            'posted_by' => null,
            'posted_at' => null,
            'source_type' => null,
            'source_id' => null,
        ]);

        VendorPaymentAllocation::query()->create([
            'vendor_payment_id' => $payment->id,
            'vendor_invoice_id' => $invoice->id,
            'allocated_amount' => 750,
        ]);

        /** @var PostVendorPaymentService $service */
        $service = app(PostVendorPaymentService::class);

        $postedJournal = $service->post($payment, $actor, true);

        $this->assertSame('posted', $postedJournal->status);

        $payment->refresh();
        $this->assertSame('approved', $payment->status);
        $this->assertNotNull($payment->approved_at);
        $this->assertNotNull($payment->posted_at);
    }
}
