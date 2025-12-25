<?php

namespace App\Services\Accounting\AP;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\VendorInvoice;
use App\Models\VendorInvoiceLine;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class VendorInvoiceService
{
    /**
     * @param  array<string, mixed>  $invoiceAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function createDraft(array $invoiceAttributes, array $linesAttributes, User $actor): VendorInvoice
    {
        return DB::transaction(function () use ($invoiceAttributes, $linesAttributes, $actor): VendorInvoice {
            if (count($linesAttributes) < 1) {
                throw new \DomainException('Vendor invoice must have at least one line.');
            }

            $purchaseOrderId = $invoiceAttributes['purchase_order_id'] ?? null;
            if ($purchaseOrderId !== null) {
                $this->assertValidPurchaseOrderLink(
                    purchaseOrderId: (int) $purchaseOrderId,
                    companyId: (int) $invoiceAttributes['company_id'],
                    vendorId: (int) $invoiceAttributes['vendor_id'],
                );
            }

            $invoice = VendorInvoice::query()->create([
                'company_id' => $invoiceAttributes['company_id'],
                'vendor_id' => $invoiceAttributes['vendor_id'],
                'purchase_order_id' => $purchaseOrderId,
                'invoice_number' => $invoiceAttributes['invoice_number'],
                'invoice_date' => $invoiceAttributes['invoice_date'],
                'due_date' => $invoiceAttributes['due_date'],
                'status' => 'draft',
                'currency_code' => $invoiceAttributes['currency_code'],
                'exchange_rate' => $invoiceAttributes['exchange_rate'] ?? 1,
                'created_by' => $actor->id,
                'approved_by' => null,
                'approved_at' => null,
                'source_type' => $invoiceAttributes['source_type'] ?? null,
                'source_id' => $invoiceAttributes['source_id'] ?? null,
                'subtotal' => 0,
                'tax_amount' => (string) ($invoiceAttributes['tax_amount'] ?? 0),
                'total_amount' => 0,
            ]);

            $this->replaceLines($invoice, $linesAttributes);
            $this->recalculateTotals($invoice, $invoiceAttributes['tax_amount'] ?? null);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_invoice.create',
                table: 'vendor_invoices',
                recordId: (int) $invoice->id,
                oldValue: null,
                newValue: [
                    'status' => $invoice->status,
                    'subtotal' => (string) $invoice->subtotal,
                    'tax_amount' => (string) $invoice->tax_amount,
                    'total_amount' => (string) $invoice->total_amount,
                ],
            );

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $invoiceAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function updateDraft(VendorInvoice $invoice, array $invoiceAttributes, array $linesAttributes, User $actor): VendorInvoice
    {
        return DB::transaction(function () use ($invoice, $invoiceAttributes, $linesAttributes, $actor): VendorInvoice {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Posted vendor invoices cannot be edited.');
            }

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft vendor invoices can be edited.');
            }

            if (count($linesAttributes) < 1) {
                throw new \DomainException('Vendor invoice must have at least one line.');
            }

            $old = [
                'vendor_id' => $invoice->vendor_id,
                'purchase_order_id' => $invoice->purchase_order_id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'currency_code' => $invoice->currency_code,
                'exchange_rate' => (string) $invoice->exchange_rate,
                'subtotal' => (string) $invoice->subtotal,
                'tax_amount' => (string) $invoice->tax_amount,
                'total_amount' => (string) $invoice->total_amount,
            ];

            $purchaseOrderId = $invoiceAttributes['purchase_order_id'] ?? $invoice->purchase_order_id;
            if ($purchaseOrderId !== null) {
                $this->assertValidPurchaseOrderLink(
                    purchaseOrderId: (int) $purchaseOrderId,
                    companyId: (int) $invoice->company_id,
                    vendorId: (int) ($invoiceAttributes['vendor_id'] ?? $invoice->vendor_id),
                );
            }

            $invoice->fill([
                'vendor_id' => $invoiceAttributes['vendor_id'] ?? $invoice->vendor_id,
                'purchase_order_id' => $purchaseOrderId,
                'invoice_number' => $invoiceAttributes['invoice_number'] ?? $invoice->invoice_number,
                'invoice_date' => $invoiceAttributes['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $invoiceAttributes['due_date'] ?? $invoice->due_date,
                'currency_code' => $invoiceAttributes['currency_code'] ?? $invoice->currency_code,
                'exchange_rate' => $invoiceAttributes['exchange_rate'] ?? $invoice->exchange_rate,
                'source_type' => $invoiceAttributes['source_type'] ?? $invoice->source_type,
                'source_id' => $invoiceAttributes['source_id'] ?? $invoice->source_id,
            ]);
            $invoice->save();

            $this->replaceLines($invoice, $linesAttributes);
            $this->recalculateTotals($invoice, $invoiceAttributes['tax_amount'] ?? null);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_invoice.update',
                table: 'vendor_invoices',
                recordId: (int) $invoice->id,
                oldValue: $old,
                newValue: [
                    'vendor_id' => $invoice->vendor_id,
                    'purchase_order_id' => $invoice->purchase_order_id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'due_date' => $invoice->due_date?->format('Y-m-d'),
                    'currency_code' => $invoice->currency_code,
                    'exchange_rate' => (string) $invoice->exchange_rate,
                    'subtotal' => (string) $invoice->subtotal,
                    'tax_amount' => (string) $invoice->tax_amount,
                    'total_amount' => (string) $invoice->total_amount,
                ],
            );

            return $invoice;
        });
    }

    public function deleteDraft(VendorInvoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Posted vendor invoices cannot be deleted.');
            }

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft vendor invoices can be deleted.');
            }

            $invoice->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_invoice.delete',
                table: 'vendor_invoices',
                recordId: (int) $invoice->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    private function replaceLines(VendorInvoice $invoice, array $linesAttributes): void
    {
        VendorInvoiceLine::query()
            ->where('vendor_invoice_id', $invoice->id)
            ->delete();

        foreach ($linesAttributes as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? 0);

            if ($qty <= 0) {
                throw new \DomainException('Line qty must be greater than 0.');
            }

            if ($unitPrice < 0) {
                throw new \DomainException('Line unit_price cannot be negative.');
            }

            $lineTotal = round($qty * $unitPrice, 2);

            VendorInvoiceLine::query()->create([
                'vendor_invoice_id' => $invoice->id,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'qty' => number_format($qty, 2, '.', ''),
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'tax_id' => $line['tax_id'] ?? null,
            ]);
        }
    }

    private function recalculateTotals(VendorInvoice $invoice, mixed $taxAmountOverride): void
    {
        $subtotal = (float) VendorInvoiceLine::query()
            ->where('vendor_invoice_id', $invoice->id)
            ->sum('line_total');

        $taxAmount = $taxAmountOverride === null ? (float) $invoice->tax_amount : (float) $taxAmountOverride;

        $invoice->subtotal = number_format(round($subtotal, 2), 2, '.', '');
        $invoice->tax_amount = number_format(round($taxAmount, 2), 2, '.', '');
        $invoice->total_amount = number_format(round($subtotal + $taxAmount, 2), 2, '.', '');
        $invoice->save();
    }

    private function assertValidPurchaseOrderLink(int $purchaseOrderId, int $companyId, int $vendorId): void
    {
        $po = PurchaseOrder::query()->where('id', $purchaseOrderId)->first();
        if ($po === null) {
            throw new \DomainException('Purchase order not found.');
        }

        if ((int) $po->company_id !== $companyId) {
            throw new \DomainException('Purchase order company mismatch.');
        }

        if ((int) $po->vendor_id !== $vendorId) {
            throw new \DomainException('Purchase order vendor mismatch.');
        }

        if ($po->status !== 'approved') {
            throw new \DomainException('Purchase order must be approved before linking to a vendor invoice.');
        }
    }
}
