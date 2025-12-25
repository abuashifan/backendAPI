<?php

namespace App\Services\Accounting\Payments;

use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CustomerPaymentService
{
    /**
     * @param  array<string, mixed>  $paymentAttributes
     * @param  array<int, array<string, mixed>>  $allocationsAttributes
     */
    public function createDraft(array $paymentAttributes, array $allocationsAttributes, User $actor): CustomerPayment
    {
        return DB::transaction(function () use ($paymentAttributes, $allocationsAttributes, $actor): CustomerPayment {
            $payment = CustomerPayment::query()->create([
                'company_id' => $paymentAttributes['company_id'],
                'customer_id' => $paymentAttributes['customer_id'],
                'receipt_number' => $paymentAttributes['receipt_number'],
                'receipt_date' => $paymentAttributes['receipt_date'],
                'receipt_method' => $paymentAttributes['receipt_method'],
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
                action: 'customer_payment.create',
                table: 'customer_payments',
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
    public function updateDraft(CustomerPayment $payment, array $paymentAttributes, array $allocationsAttributes, User $actor): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $paymentAttributes, $allocationsAttributes, $actor): CustomerPayment {
            $payment->refresh();

            if ($payment->posted_at !== null) {
                throw new \DomainException('Posted customer payments cannot be edited.');
            }

            if ($payment->status !== 'draft') {
                throw new \DomainException('Only draft customer payments can be edited.');
            }

            $old = [
                'customer_id' => $payment->customer_id,
                'receipt_number' => $payment->receipt_number,
                'receipt_date' => $payment->receipt_date?->format('Y-m-d'),
                'receipt_method' => $payment->receipt_method,
                'amount' => (string) $payment->amount,
                'currency_code' => $payment->currency_code,
                'exchange_rate' => (string) $payment->exchange_rate,
                'notes' => $payment->notes,
            ];

            $payment->fill([
                'customer_id' => $paymentAttributes['customer_id'] ?? $payment->customer_id,
                'receipt_number' => $paymentAttributes['receipt_number'] ?? $payment->receipt_number,
                'receipt_date' => $paymentAttributes['receipt_date'] ?? $payment->receipt_date,
                'receipt_method' => $paymentAttributes['receipt_method'] ?? $payment->receipt_method,
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
                action: 'customer_payment.update',
                table: 'customer_payments',
                recordId: (int) $payment->id,
                oldValue: $old,
                newValue: [
                    'customer_id' => $payment->customer_id,
                    'receipt_number' => $payment->receipt_number,
                    'receipt_date' => $payment->receipt_date?->format('Y-m-d'),
                    'receipt_method' => $payment->receipt_method,
                    'amount' => (string) $payment->amount,
                    'currency_code' => $payment->currency_code,
                    'exchange_rate' => (string) $payment->exchange_rate,
                    'notes' => $payment->notes,
                ],
            );

            return $payment;
        });
    }

    public function deleteDraft(CustomerPayment $payment, User $actor): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment->refresh();

            if ($payment->posted_at !== null) {
                throw new \DomainException('Posted customer payments cannot be deleted.');
            }

            if ($payment->status !== 'draft') {
                throw new \DomainException('Only draft customer payments can be deleted.');
            }

            $payment->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'customer_payment.delete',
                table: 'customer_payments',
                recordId: (int) $payment->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocationsAttributes
     */
    private function replaceAllocations(CustomerPayment $payment, array $allocationsAttributes): void
    {
        CustomerPaymentAllocation::query()
            ->where('customer_payment_id', $payment->id)
            ->delete();

        $totalAllocated = 0.0;

        foreach ($allocationsAttributes as $allocation) {
            $invoiceId = (int) $allocation['sales_invoice_id'];
            $allocated = (float) ($allocation['allocated_amount'] ?? 0);

            if ($allocated <= 0) {
                throw new \DomainException('Allocated amount must be greater than 0.');
            }

            /** @var SalesInvoice|null $invoice */
            $invoice = SalesInvoice::query()->find($invoiceId);
            if ($invoice === null) {
                throw new \DomainException('Sales invoice not found.');
            }

            if ((int) $invoice->company_id !== (int) $payment->company_id) {
                throw new \DomainException('Sales invoice company mismatch.');
            }

            if ((int) $invoice->customer_id !== (int) $payment->customer_id) {
                throw new \DomainException('Sales invoice customer mismatch.');
            }

            if ($invoice->posted_at === null) {
                throw new \DomainException('Sales invoice must be posted before allocating payment.');
            }

            $totalAllocated += $allocated;

            CustomerPaymentAllocation::query()->create([
                'customer_payment_id' => $payment->id,
                'sales_invoice_id' => $invoice->id,
                'allocated_amount' => number_format(round($allocated, 2), 2, '.', ''),
            ]);
        }

        if ($totalAllocated > (float) $payment->amount) {
            throw new \DomainException('Allocated amount exceeds payment amount.');
        }
    }
}
