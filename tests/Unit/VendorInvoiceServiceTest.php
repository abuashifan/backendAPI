<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\Accounting\AP\PurchaseOrderService;
use App\Services\Accounting\AP\VendorInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{company:Company, vendor:Vendor, actor:User}
     */
    private function makeCompanyVendorAndActor(): array
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

        return compact('company', 'vendor', 'actor');
    }

    private function makeApprovedPo(array $ctx): PurchaseOrder
    {
        /** @var PurchaseOrderService $poService */
        $poService = app(PurchaseOrderService::class);

        $po = $poService->createDraft(
            poAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'po_number' => 'PO-100',
                'po_date' => '2025-12-10',
                'expected_date' => null,
                'currency_code' => 'IDR',
            ],
            linesAttributes: [
                ['description' => 'Item A', 'qty' => 1, 'unit_price' => 1000],
            ],
            actor: $ctx['actor'],
        );

        return $poService->approve($po, $ctx['actor']);
    }

    public function test_create_and_update_draft_vendor_invoice_recalculates_totals(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();
        $po = $this->makeApprovedPo($ctx);

        /** @var VendorInvoiceService $service */
        $service = app(VendorInvoiceService::class);

        $invoice = $service->createDraft(
            invoiceAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'purchase_order_id' => $po->id,
                'invoice_number' => 'INV-001',
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

        $this->assertInstanceOf(VendorInvoice::class, $invoice);
        $this->assertSame('draft', $invoice->status);
        $this->assertSame('2000.00', (string) $invoice->total_amount);

        $updated = $service->updateDraft(
            invoice: $invoice,
            invoiceAttributes: ['invoice_number' => 'INV-001-REV'],
            linesAttributes: [
                ['description' => 'B', 'qty' => 1, 'unit_price' => 500],
                ['description' => 'C', 'qty' => 3, 'unit_price' => 100],
            ],
            actor: $ctx['actor'],
        );

        $this->assertSame('INV-001-REV', $updated->invoice_number);
        $this->assertSame('800.00', (string) $updated->total_amount);
        $this->assertDatabaseCount('vendor_invoice_lines', 2);
    }

    public function test_cannot_link_to_unapproved_purchase_order(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();

        $po = PurchaseOrder::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $ctx['vendor']->id,
            'po_number' => 'PO-DRAFT',
            'po_date' => '2025-12-10',
            'expected_date' => null,
            'status' => 'draft',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'currency_code' => 'IDR',
            'notes' => null,
            'created_by' => $ctx['actor']->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        /** @var VendorInvoiceService $service */
        $service = app(VendorInvoiceService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Purchase order must be approved before linking to a vendor invoice.');

        $service->createDraft(
            invoiceAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'purchase_order_id' => $po->id,
                'invoice_number' => 'INV-PO-DRAFT',
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
    }

    public function test_update_blocks_when_invoice_is_not_draft(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();
        $po = $this->makeApprovedPo($ctx);

        /** @var VendorInvoiceService $service */
        $service = app(VendorInvoiceService::class);

        $invoice = $service->createDraft(
            invoiceAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'purchase_order_id' => $po->id,
                'invoice_number' => 'INV-LOCK',
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
        $this->expectExceptionMessage('Only draft vendor invoices can be edited.');

        $service->updateDraft($invoice, [], [['description' => 'B', 'qty' => 1, 'unit_price' => 1]], $ctx['actor']);
    }
}
