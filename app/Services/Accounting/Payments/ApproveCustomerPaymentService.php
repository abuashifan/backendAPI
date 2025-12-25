<?php

namespace App\Services\Accounting\Payments;

use App\Models\AccountingPeriod;
use App\Models\CustomerPayment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveCustomerPaymentService
{
    public function approve(CustomerPayment $payment, User $actor): CustomerPayment
    {
        return DB::transaction(function () use ($payment, $actor): CustomerPayment {
            $payment->refresh();

            $old = [
                'status' => $payment->status,
                'approved_by' => $payment->approved_by,
                'approved_at' => $payment->approved_at?->toISOString(),
            ];

            if ($payment->status !== 'draft') {
                throw new \DomainException('Only draft customer payments can be approved.');
            }

            $period = $this->findOpenPeriodForDate($payment->company_id, $payment->receipt_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $payment->status = 'approved';
            $payment->approved_by = $actor->id;
            $payment->approved_at = now();
            $payment->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'customer_payment.approve',
                table: 'customer_payments',
                recordId: (int) $payment->id,
                oldValue: $old,
                newValue: [
                    'status' => $payment->status,
                    'approved_by' => $payment->approved_by,
                    'approved_at' => $payment->approved_at?->toISOString(),
                ],
            );

            return $payment;
        });
    }

    private function findOpenPeriodForDate(int $companyId, \DateTimeInterface $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();
    }
}
