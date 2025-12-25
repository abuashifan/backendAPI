<?php

namespace Tests\Unit;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Journal;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Accounting\Payments\PostCustomerPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostCustomerPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company:Company, period:AccountingPeriod, cash:ChartOfAccount, ar:ChartOfAccount}
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

        $ar = ChartOfAccount::query()->create([
            'company_id' => $company->id,
            'code' => '1-1300',
            'name' => 'Accounts Receivable',
            'type' => 'asset',
            'normal_balance' => 'debit',
            'parent_id' => null,
            'level' => 2,
            'is_postable' => true,
            'is_active' => true,
        ]);

        return compact('company', 'period', 'cash', 'ar');
    }

    public function test_can_post_customer_payment_creates_posted_journal_and_marks_payment_posted(): void
    {
        $ctx = $this->makeCompanyPeriodAndAccounts();

        $actor = User::factory()->create();

        $customer = Customer::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'C001',
            'name' => 'Customer A',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-PAID',
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

        $payment = CustomerPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-001',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
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

        CustomerPaymentAllocation::query()->create([
            'customer_payment_id' => $payment->id,
            'sales_invoice_id' => $invoice->id,
            'allocated_amount' => 1000,
        ]);

        /** @var PostCustomerPaymentService $service */
        $service = app(PostCustomerPaymentService::class);

        $postedJournal = $service->post($payment, $actor);

        $this->assertInstanceOf(Journal::class, $postedJournal);
        $this->assertSame('posted', $postedJournal->status);
        $this->assertSame('ar.customer_payment', $postedJournal->source_type);
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

        $customer = Customer::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'C002',
            'name' => 'Customer B',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-PAID-2',
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

        $payment = CustomerPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-002',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
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

        CustomerPaymentAllocation::query()->create([
            'customer_payment_id' => $payment->id,
            'sales_invoice_id' => $invoice->id,
            'allocated_amount' => 500,
        ]);

        /** @var PostCustomerPaymentService $service */
        $service = app(PostCustomerPaymentService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Accounting period is closed.');

        $service->post($payment, $actor);
    }

    public function test_can_auto_approve_and_post_from_draft_when_enabled(): void
    {
        $ctx = $this->makeCompanyPeriodAndAccounts();

        $actor = User::factory()->create();

        $customer = Customer::query()->create([
            'company_id' => $ctx['company']->id,
            'code' => 'C003',
            'name' => 'Customer C',
            'email' => null,
            'phone' => null,
            'address' => null,
            'tax_id' => null,
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = SalesInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'invoice_number' => 'SI-PAID-3',
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

        $payment = CustomerPayment::query()->create([
            'company_id' => $ctx['company']->id,
            'customer_id' => $customer->id,
            'receipt_number' => 'RCPT-003',
            'receipt_date' => '2025-12-15',
            'receipt_method' => 'cash',
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

        CustomerPaymentAllocation::query()->create([
            'customer_payment_id' => $payment->id,
            'sales_invoice_id' => $invoice->id,
            'allocated_amount' => 750,
        ]);

        /** @var PostCustomerPaymentService $service */
        $service = app(PostCustomerPaymentService::class);

        $postedJournal = $service->post($payment, $actor, true);

        $this->assertSame('posted', $postedJournal->status);

        $payment->refresh();
        $this->assertSame('approved', $payment->status);
        $this->assertNotNull($payment->approved_at);
        $this->assertNotNull($payment->posted_at);
    }
}
