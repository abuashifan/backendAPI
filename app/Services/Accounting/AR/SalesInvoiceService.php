<?php

namespace App\Services\Accounting\AR;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    /**
     * @param  array<string, mixed>  $invoiceAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function createDraft(array $invoiceAttributes, array $linesAttributes, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoiceAttributes, $linesAttributes, $actor): SalesInvoice {
            if (count($linesAttributes) < 1) {
                throw new \DomainException('Sales invoice must have at least one line.');
            }

            $invoice = SalesInvoice::query()->create([
                'company_id' => $invoiceAttributes['company_id'],
                'customer_id' => $invoiceAttributes['customer_id'],
                'invoice_number' => $invoiceAttributes['invoice_number'],
                'invoice_date' => $invoiceAttributes['invoice_date'],
                'due_date' => $invoiceAttributes['due_date'],
                'status' => 'draft',
                'currency_code' => $invoiceAttributes['currency_code'],
                'exchange_rate' => $invoiceAttributes['exchange_rate'] ?? 1,
                'created_by' => $actor->id,
                'approved_by' => null,
                'approved_at' => null,
                'posted_by' => null,
                'posted_at' => null,
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
                action: 'sales_invoice.create',
                table: 'sales_invoices',
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
    public function updateDraft(SalesInvoice $invoice, array $invoiceAttributes, array $linesAttributes, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $invoiceAttributes, $linesAttributes, $actor): SalesInvoice {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Posted sales invoices cannot be edited.');
            }

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft sales invoices can be edited.');
            }

            if (count($linesAttributes) < 1) {
                throw new \DomainException('Sales invoice must have at least one line.');
            }

            $old = [
                'customer_id' => $invoice->customer_id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                'due_date' => $invoice->due_date?->format('Y-m-d'),
                'currency_code' => $invoice->currency_code,
                'exchange_rate' => (string) $invoice->exchange_rate,
                'subtotal' => (string) $invoice->subtotal,
                'tax_amount' => (string) $invoice->tax_amount,
                'total_amount' => (string) $invoice->total_amount,
            ];

            $invoice->fill([
                'customer_id' => $invoiceAttributes['customer_id'] ?? $invoice->customer_id,
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
                action: 'sales_invoice.update',
                table: 'sales_invoices',
                recordId: (int) $invoice->id,
                oldValue: $old,
                newValue: [
                    'customer_id' => $invoice->customer_id,
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

    public function deleteDraft(SalesInvoice $invoice, User $actor): void
    {
        DB::transaction(function () use ($invoice, $actor): void {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Posted sales invoices cannot be deleted.');
            }

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft sales invoices can be deleted.');
            }

            $invoice->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'sales_invoice.delete',
                table: 'sales_invoices',
                recordId: (int) $invoice->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    private function replaceLines(SalesInvoice $invoice, array $linesAttributes): void
    {
        SalesInvoiceLine::query()
            ->where('sales_invoice_id', $invoice->id)
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

            SalesInvoiceLine::query()->create([
                'sales_invoice_id' => $invoice->id,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'qty' => number_format($qty, 2, '.', ''),
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'tax_id' => $line['tax_id'] ?? null,
            ]);
        }
    }

    private function recalculateTotals(SalesInvoice $invoice, mixed $taxAmountOverride): void
    {
        $subtotal = (float) SalesInvoiceLine::query()
            ->where('sales_invoice_id', $invoice->id)
            ->sum('line_total');

        $taxAmount = $taxAmountOverride === null ? (float) $invoice->tax_amount : (float) $taxAmountOverride;

        $invoice->subtotal = number_format(round($subtotal, 2), 2, '.', '');
        $invoice->tax_amount = number_format(round($taxAmount, 2), 2, '.', '');
        $invoice->total_amount = number_format(round($subtotal + $taxAmount, 2), 2, '.', '');
        $invoice->save();
    }
}
