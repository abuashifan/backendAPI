<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Accounting\AR\SalesInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company:Company, customer:Customer, actor:User}
     */
    private function makeCompanyCustomerAndActor(): array
    {
        $company = Company::query()->create([
            'code' => 'CMP001',
            'name' => 'Test Company',
            'base_currency' => 'IDR',
            'fiscal_year_start_month' => 1,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);

        $actor = User::factory()->create();

        $customer = Customer::query()->create([
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

        return compact('company', 'customer', 'actor');
    }

    public function test_create_and_update_draft_sales_invoice_recalculates_totals(): void
    {
        $ctx = $this->makeCompanyCustomerAndActor();

        /** @var SalesInvoiceService $service */
        $service = app(SalesInvoiceService::class);

        $invoice = $service->createDraft(
            invoiceAttributes: [
                'company_id' => $ctx['company']->id,
                'customer_id' => $ctx['customer']->id,
                'invoice_number' => 'SI-001',
                'invoice_date' => '2025-12-10',
                'due_date' => '2026-01-09',
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
            ],
            linesAttributes: [
                ['description' => 'A', 'qty' => 2, 'unit_price' => 1000],
            ],
            actor: $ctx['actor'],
        );

        $this->assertInstanceOf(SalesInvoice::class, $invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('2000.00', (string) $invoice->total_amount);

        $updated = $service->updateDraft(
            invoice: $invoice,
            invoiceAttributes: ['invoice_number' => 'SI-001-REV', 'tax_amount' => 100],
            linesAttributes: [
                ['description' => 'B', 'qty' => 1, 'unit_price' => 500],
                ['description' => 'C', 'qty' => 3, 'unit_price' => 100],
            ],
            actor: $ctx['actor'],
        );

        $this->assertSame('SI-001-REV', $updated->invoice_number);
        $this->assertSame('900.00', (string) $updated->total_amount);
        $this->assertDatabaseCount('sales_invoice_lines', 2);
    }

    public function test_update_blocks_when_invoice_is_not_draft(): void
    {
        $ctx = $this->makeCompanyCustomerAndActor();

        /** @var SalesInvoiceService $service */
        $service = app(SalesInvoiceService::class);

        $invoice = $service->createDraft(
            invoiceAttributes: [
                'company_id' => $ctx['company']->id,
                'customer_id' => $ctx['customer']->id,
                'invoice_number' => 'SI-LOCK',
                'invoice_date' => '2025-12-10',
                'due_date' => '2026-01-09',
                'currency_code' => 'IDR',
                'exchange_rate' => 1,
            ],
            linesAttributes: [
                ['description' => 'A', 'qty' => 1, 'unit_price' => 1000],
            ],
            actor: $ctx['actor'],
        );

        $invoice->status = 'approved';
        $invoice->approved_by = $ctx['actor']->id;
        $invoice->approved_at = now();
        $invoice->save();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only draft sales invoices can be edited.');

        $service->updateDraft($invoice, [], [['description' => 'B', 'qty' => 1, 'unit_price' => 1]], $ctx['actor']);
    }
}
