<?php

namespace App\Services\Accounting\Payments;

use App\Models\User;
use App\Models\VendorInvoice;
use App\Models\VendorPayment;
use App\Models\VendorPaymentAllocation;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class VendorPaymentService
{
    /**
     * @param  array<string, mixed>  $paymentAttributes
     * @param  array<int, array<string, mixed>>  $allocationsAttributes
     */
    public function createDraft(array $paymentAttributes, array $allocationsAttributes, User $actor): VendorPayment
    {
        return DB::transaction(function () use ($paymentAttributes, $allocationsAttributes, $actor): VendorPayment {
            $payment = VendorPayment::query()->create([
                'company_id' => $paymentAttributes['company_id'],
                'vendor_id' => $paymentAttributes['vendor_id'],
                'payment_number' => $paymentAttributes['payment_number'],
                'payment_date' => $paymentAttributes['payment_date'],
                'payment_method' => $paymentAttributes['payment_method'],
                'amount' => $paymentAttributes['amount'],
                'currency_code' => $paymentAttributes['currency_code'],
                'exchange_rate' => $paymentAttributes['exchange_rate'] ?? 1,
                'status' => 'draft',
                'notes' => $paymentAttributes['notes'] ?? null,
                'created_by' => $actor->id,
                'approved_by' => null,
                'approved_at' => null,
                'posted_by' => null,
                'posted_at' => null,
                'source_type' => $paymentAttributes['source_type'] ?? null,
                'source_id' => $paymentAttributes['source_id'] ?? null,
            ]);

            $this->replaceAllocations($payment, $allocationsAttributes);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_payment.create',
                table: 'vendor_payments',
                recordId: (int) $payment->id,
                oldValue: null,
                newValue: [
                    'status' => $payment->status,
                    'amount' => (string) $payment->amount,
                ],
            );

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $paymentAttributes
     * @param  array<int, array<string, mixed>>  $allocationsAttributes
     */
    public function updateDraft(VendorPayment $payment, array $paymentAttributes, array $allocationsAttributes, User $actor): VendorPayment
    {
        return DB::transaction(function () use ($payment, $paymentAttributes, $allocationsAttributes, $actor): VendorPayment {
            $payment->refresh();

            if ($payment->posted_at !== null) {
                throw new \DomainException('Posted vendor payments cannot be edited.');
            }

            if ($payment->status !== 'draft') {
                throw new \DomainException('Only draft vendor payments can be edited.');
            }

            $old = [
                'vendor_id' => $payment->vendor_id,
                'payment_number' => $payment->payment_number,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'payment_method' => $payment->payment_method,
                'amount' => (string) $payment->amount,
                'currency_code' => $payment->currency_code,
                'exchange_rate' => (string) $payment->exchange_rate,
                'notes' => $payment->notes,
            ];

            $payment->fill([
                'vendor_id' => $paymentAttributes['vendor_id'] ?? $payment->vendor_id,
                'payment_number' => $paymentAttributes['payment_number'] ?? $payment->payment_number,
                'payment_date' => $paymentAttributes['payment_date'] ?? $payment->payment_date,
                'payment_method' => $paymentAttributes['payment_method'] ?? $payment->payment_method,
                'amount' => $paymentAttributes['amount'] ?? $payment->amount,
                'currency_code' => $paymentAttributes['currency_code'] ?? $payment->currency_code,
                'exchange_rate' => $paymentAttributes['exchange_rate'] ?? $payment->exchange_rate,
                'notes' => $paymentAttributes['notes'] ?? $payment->notes,
                'source_type' => $paymentAttributes['source_type'] ?? $payment->source_type,
                'source_id' => $paymentAttributes['source_id'] ?? $payment->source_id,
            ]);
            $payment->save();

            $this->replaceAllocations($payment, $allocationsAttributes);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_payment.update',
                table: 'vendor_payments',
                recordId: (int) $payment->id,
                oldValue: $old,
                newValue: [
                    'vendor_id' => $payment->vendor_id,
                    'payment_number' => $payment->payment_number,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'payment_method' => $payment->payment_method,
                    'amount' => (string) $payment->amount,
                    'currency_code' => $payment->currency_code,
                    'exchange_rate' => (string) $payment->exchange_rate,
                    'notes' => $payment->notes,
                ],
            );

            return $payment;
        });
    }

    public function deleteDraft(VendorPayment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment->refresh();

            if ($payment->posted_at !== null) {
                throw new \DomainException('Posted vendor payments cannot be deleted.');
            }

            if ($payment->status !== 'draft') {
                throw new \DomainException('Only draft vendor payments can be deleted.');
            }

            $payment->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_payment.delete',
                table: 'vendor_payments',
                recordId: (int) $payment->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocationsAttributes
     */
    private function replaceAllocations(VendorPayment $payment, array $allocationsAttributes): void
    {
        VendorPaymentAllocation::query()
            ->where('vendor_payment_id', $payment->id)
            ->delete();

        $totalAllocated = 0.0;

        foreach ($allocationsAttributes as $allocation) {
            $invoiceId = (int) $allocation['vendor_invoice_id'];
            $allocated = (float) ($allocation['allocated_amount'] ?? 0);

            if ($allocated <= 0) {
                throw new \DomainException('Allocated amount must be greater than 0.');
            }

            /** @var VendorInvoice|null $invoice */
            $invoice = VendorInvoice::query()->find($invoiceId);
            if ($invoice === null) {
                throw new \DomainException('Vendor invoice not found.');
            }

            if ((int) $invoice->company_id !== (int) $payment->company_id) {
                throw new \DomainException('Vendor invoice company mismatch.');
            }

            if ((int) $invoice->vendor_id !== (int) $payment->vendor_id) {
                throw new \DomainException('Vendor invoice vendor mismatch.');
            }

            if ($invoice->posted_at === null) {
                throw new \DomainException('Vendor invoice must be posted before allocating payment.');
            }

            $totalAllocated += $allocated;

            VendorPaymentAllocation::query()->create([
                'vendor_payment_id' => $payment->id,
                'vendor_invoice_id' => $invoice->id,
                'allocated_amount' => number_format(round($allocated, 2), 2, '.', ''),
            ]);
        }

        if ($totalAllocated > (float) $payment->amount) {
            throw new \DomainException('Allocated amount exceeds payment amount.');
        }
    }
}
