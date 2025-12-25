<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use App\Services\Accounting\AP\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
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

    public function test_create_draft_purchase_order_creates_lines_and_calculates_totals(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();

        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $po = $service->createDraft(
            poAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'po_number' => 'PO-001',
                'po_date' => '2025-12-10',
                'expected_date' => '2025-12-20',
                'currency_code' => 'IDR',
                'notes' => 'Test PO',
            ],
            linesAttributes: [
                ['description' => 'Item A', 'qty' => 2, 'unit_price' => 1000],
                ['description' => 'Item B', 'qty' => 1, 'unit_price' => 500],
            ],
            actor: $ctx['actor'],
        );

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertSame('draft', $po->status);
        $this->assertSame('2500.00', (string) $po->total_amount);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'company_id' => $ctx['company']->id,
            'vendor_id' => $ctx['vendor']->id,
            'po_number' => 'PO-001',
            'status' => 'draft',
        ]);

        $this->assertDatabaseCount('purchase_order_lines', 2);
    }

    public function test_approve_purchase_order_sets_approved_fields_and_locks_edits(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();

        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $po = $service->createDraft(
            poAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'po_number' => 'PO-002',
                'po_date' => '2025-12-10',
                'expected_date' => null,
                'currency_code' => 'IDR',
            ],
            linesAttributes: [
                ['description' => 'Item A', 'qty' => 1, 'unit_price' => 1000],
            ],
            actor: $ctx['actor'],
        );

        $approved = $service->approve($po, $ctx['actor']);

        $this->assertSame('approved', $approved->status);
        $this->assertSame($ctx['actor']->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only draft purchase orders can be edited.');

        $service->updateDraft($approved, ['notes' => 'x'], [['description' => 'Item', 'qty' => 1, 'unit_price' => 1]], $ctx['actor']);
    }

    public function test_cancel_blocks_when_there_is_posted_vendor_invoice(): void
    {
        $ctx = $this->makeCompanyVendorAndActor();

        /** @var PurchaseOrderService $service */
        $service = app(PurchaseOrderService::class);

        $po = $service->createDraft(
            poAttributes: [
                'company_id' => $ctx['company']->id,
                'vendor_id' => $ctx['vendor']->id,
                'po_number' => 'PO-003',
                'po_date' => '2025-12-10',
                'expected_date' => null,
                'currency_code' => 'IDR',
            ],
            linesAttributes: [
                ['description' => 'Item A', 'qty' => 1, 'unit_price' => 1000],
            ],
            actor: $ctx['actor'],
        );
        $po = $service->approve($po, $ctx['actor']);

        $invoice = VendorInvoice::query()->create([
            'company_id' => $ctx['company']->id,
            'vendor_id' => $ctx['vendor']->id,
            'purchase_order_id' => $po->id,
            'invoice_number' => 'INV-PO-003',
            'invoice_date' => '2025-12-10',
            'due_date' => '2026-01-09',
            'status' => 'approved',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total_amount' => 1000,
            'currency_code' => 'IDR',
            'exchange_rate' => 1,
            'created_by' => $ctx['actor']->id,
            'approved_by' => $ctx['actor']->id,
            'approved_at' => now(),
            'posted_by' => $ctx['actor']->id,
            'posted_at' => now(),
            'source_type' => null,
            'source_id' => null,
        ]);

        $this->assertNotNull($invoice->posted_at);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot cancel purchase order with posted vendor invoices.');

        $service->cancel($po, $ctx['actor']);
    }
}
