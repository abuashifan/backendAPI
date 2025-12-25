<?php

namespace App\Services\Accounting\AP;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * @param  array<string, mixed>  $poAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function createDraft(array $poAttributes, array $linesAttributes, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($poAttributes, $linesAttributes, $actor): PurchaseOrder {
            if (count($linesAttributes) < 1) {
                throw new \DomainException('Purchase order must have at least one line.');
            }

            $po = PurchaseOrder::query()->create([
                'company_id' => $poAttributes['company_id'],
                'vendor_id' => $poAttributes['vendor_id'],
                'po_number' => $poAttributes['po_number'],
                'po_date' => $poAttributes['po_date'],
                'expected_date' => $poAttributes['expected_date'] ?? null,
                'status' => 'draft',
                'currency_code' => $poAttributes['currency_code'],
                'notes' => $poAttributes['notes'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => null,
                'approved_at' => null,
                'subtotal' => 0,
                'tax_amount' => (string) ($poAttributes['tax_amount'] ?? 0),
                'total_amount' => 0,
            ]);

            $this->replaceLines($po, $linesAttributes);
            $this->recalculateTotals($po, $poAttributes['tax_amount'] ?? null);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'purchase_order.create',
                table: 'purchase_orders',
                recordId: (int) $po->id,
                oldValue: null,
                newValue: [
                    'status' => $po->status,
                    'subtotal' => (string) $po->subtotal,
                    'tax_amount' => (string) $po->tax_amount,
                    'total_amount' => (string) $po->total_amount,
                ],
            );

            return $po;
        });
    }

    /**
     * @param  array<string, mixed>  $poAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function updateDraft(PurchaseOrder $po, array $poAttributes, array $linesAttributes, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $poAttributes, $linesAttributes, $actor): PurchaseOrder {
            $po->refresh();

            if ($po->status !== 'draft') {
                throw new \DomainException('Only draft purchase orders can be edited.');
            }

            if (count($linesAttributes) < 1) {
                throw new \DomainException('Purchase order must have at least one line.');
            }

            $old = [
                'vendor_id' => $po->vendor_id,
                'po_number' => $po->po_number,
                'po_date' => $po->po_date?->format('Y-m-d'),
                'expected_date' => $po->expected_date?->format('Y-m-d'),
                'currency_code' => $po->currency_code,
                'notes' => $po->notes,
                'subtotal' => (string) $po->subtotal,
                'tax_amount' => (string) $po->tax_amount,
                'total_amount' => (string) $po->total_amount,
            ];

            $po->fill([
                'vendor_id' => $poAttributes['vendor_id'] ?? $po->vendor_id,
                'po_number' => $poAttributes['po_number'] ?? $po->po_number,
                'po_date' => $poAttributes['po_date'] ?? $po->po_date,
                'expected_date' => $poAttributes['expected_date'] ?? $po->expected_date,
                'currency_code' => $poAttributes['currency_code'] ?? $po->currency_code,
                'notes' => $poAttributes['notes'] ?? $po->notes,
            ]);
            $po->save();

            $this->replaceLines($po, $linesAttributes);
            $this->recalculateTotals($po, $poAttributes['tax_amount'] ?? null);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'purchase_order.update',
                table: 'purchase_orders',
                recordId: (int) $po->id,
                oldValue: $old,
                newValue: [
                    'vendor_id' => $po->vendor_id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date?->format('Y-m-d'),
                    'expected_date' => $po->expected_date?->format('Y-m-d'),
                    'currency_code' => $po->currency_code,
                    'notes' => $po->notes,
                    'subtotal' => (string) $po->subtotal,
                    'tax_amount' => (string) $po->tax_amount,
                    'total_amount' => (string) $po->total_amount,
                ],
            );

            return $po;
        });
    }

    public function approve(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor): PurchaseOrder {
            $po->refresh();

            $old = [
                'status' => $po->status,
                'approved_by' => $po->approved_by,
                'approved_at' => $po->approved_at?->toISOString(),
            ];

            if ($po->status !== 'draft') {
                throw new \DomainException('Only draft purchase orders can be approved.');
            }

            $po->status = 'approved';
            $po->approved_by = $actor->id;
            $po->approved_at = now();
            $po->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'purchase_order.approve',
                table: 'purchase_orders',
                recordId: (int) $po->id,
                oldValue: $old,
                newValue: [
                    'status' => $po->status,
                    'approved_by' => $po->approved_by,
                    'approved_at' => $po->approved_at?->toISOString(),
                ],
            );

            return $po;
        });
    }

    public function cancel(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $actor): PurchaseOrder {
            $po->refresh();

            $old = [
                'status' => $po->status,
            ];

            if ($po->status === 'cancelled') {
                throw new \DomainException('Purchase order is already cancelled.');
            }

            if ($po->vendorInvoices()->whereNotNull('posted_at')->exists()) {
                throw new \DomainException('Cannot cancel purchase order with posted vendor invoices.');
            }

            $po->status = 'cancelled';
            $po->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'purchase_order.cancel',
                table: 'purchase_orders',
                recordId: (int) $po->id,
                oldValue: $old,
                newValue: ['status' => $po->status],
            );

            return $po;
        });
    }

    public function deleteDraft(PurchaseOrder $po, User $actor): void
    {
        DB::transaction(function () use ($po, $actor): void {
            $po->refresh();

            if ($po->status !== 'draft') {
                throw new \DomainException('Only draft purchase orders can be deleted.');
            }

            if ($po->vendorInvoices()->exists()) {
                throw new \DomainException('Cannot delete purchase order that is referenced by vendor invoices.');
            }

            $po->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'purchase_order.delete',
                table: 'purchase_orders',
                recordId: (int) $po->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    private function replaceLines(PurchaseOrder $po, array $linesAttributes): void
    {
        PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
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

            PurchaseOrderLine::query()->create([
                'purchase_order_id' => $po->id,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'qty' => number_format($qty, 2, '.', ''),
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'tax_id' => $line['tax_id'] ?? null,
            ]);
        }
    }

    private function recalculateTotals(PurchaseOrder $po, mixed $taxAmountOverride): void
    {
        $subtotal = (float) PurchaseOrderLine::query()
            ->where('purchase_order_id', $po->id)
            ->sum('line_total');

        $taxAmount = $taxAmountOverride === null ? (float) $po->tax_amount : (float) $taxAmountOverride;

        $po->subtotal = number_format(round($subtotal, 2), 2, '.', '');
        $po->tax_amount = number_format(round($taxAmount, 2), 2, '.', '');
        $po->total_amount = number_format(round($subtotal + $taxAmount, 2), 2, '.', '');
        $po->save();
    }
}
