<?php

namespace App\Services\Accounting\AR;

use App\Models\AccountingPeriod;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveSalesInvoiceService
{
    public function approve(SalesInvoice $invoice, User $actor): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): SalesInvoice {
            $invoice->refresh();

            $old = [
                'status' => $invoice->status,
                'approved_by' => $invoice->approved_by,
                'approved_at' => $invoice->approved_at?->toISOString(),
            ];

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft sales invoices can be approved.');
            }

            $period = $this->findOpenPeriodForDate($invoice->company_id, $invoice->invoice_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $invoice->status = 'approved';
            $invoice->approved_by = $actor->id;
            $invoice->approved_at = now();
            $invoice->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'sales_invoice.approve',
                table: 'sales_invoices',
                recordId: (int) $invoice->id,
                oldValue: $old,
                newValue: [
                    'status' => $invoice->status,
                    'approved_by' => $invoice->approved_by,
                    'approved_at' => $invoice->approved_at?->toISOString(),
                ],
            );

            return $invoice;
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
