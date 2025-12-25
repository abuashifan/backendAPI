<?php

namespace App\Services\Accounting\AP;

use App\Models\AccountingPeriod;
use App\Models\VendorInvoice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveVendorInvoiceService
{
    public function approve(VendorInvoice $invoice, User $actor): VendorInvoice
    {
        return DB::transaction(function () use ($invoice, $actor): VendorInvoice {
            $invoice->refresh();

            $old = [
                'status' => $invoice->status,
                'approved_by' => $invoice->approved_by,
                'approved_at' => $invoice->approved_at?->toISOString(),
            ];

            if ($invoice->status !== 'draft') {
                throw new \DomainException('Only draft vendor invoices can be approved.');
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
                action: 'vendor_invoice.approve',
                table: 'vendor_invoices',
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
