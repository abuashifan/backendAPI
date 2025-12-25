<?php

namespace App\Services\Accounting\AP;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\VendorInvoice;
use App\Models\User;
use App\Services\Accounting\Journal\JournalService;
use App\Services\Accounting\Journal\PostJournalService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PostVendorInvoiceService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PostJournalService $postJournalService,
        private readonly ApproveVendorInvoiceService $approveVendorInvoiceService,
    ) {
    }

    /**
     * Post a vendor invoice:
     * - Requires invoice to be approved (unless autoApproveIfNeeded=true)
     * - Creates a balanced journal draft sourced from the invoice
     * - Posts the journal
     * - Marks invoice as posted (posted_by/posted_at)
     */
    public function post(VendorInvoice $invoice, User $actor, bool $autoApproveIfNeeded = false): Journal
    {
        return DB::transaction(function () use ($invoice, $actor, $autoApproveIfNeeded): Journal {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Vendor invoice is already posted.');
            }

            if ($invoice->status !== 'approved') {
                if ($invoice->status === 'draft' && $autoApproveIfNeeded) {
                    $this->approveVendorInvoiceService->approve($invoice, $actor);
                    $invoice->refresh();
                } else {
                    throw new \DomainException('Vendor invoice must be approved before posting.');
                }
            }

            $period = $this->findOpenPeriodForDate($invoice->company_id, $invoice->invoice_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $apAccount = $this->findPostingAccount($invoice->company_id, '2-1100');
            $debitAccount = $this->findPostingAccount($invoice->company_id, '1-1400');

            $baseAmount = $this->baseAmount((float) $invoice->total_amount, (float) $invoice->exchange_rate);

            $journalNumber = $this->journalNumber($invoice);

            $journal = $this->journalService->createDraft(
                actor: $actor,
                journalAttributes: [
                    'journal_number' => $journalNumber,
                    'company_id' => $invoice->company_id,
                    'period_id' => $period->id,
                    'journal_date' => $invoice->invoice_date,
                    'source_type' => 'ap.vendor_invoice',
                    'source_id' => $invoice->id,
                    'description' => 'Vendor invoice ' . $invoice->invoice_number,
                    'created_by' => $actor->id,
                ],
                linesAttributes: [
                    ['account_id' => $debitAccount->id, 'debit' => $baseAmount, 'credit' => 0],
                    ['account_id' => $apAccount->id, 'debit' => 0, 'credit' => $baseAmount],
                ],
            );

            $posted = $this->postJournalService->post($journal, $actor);

            $old = [
                'posted_by' => $invoice->posted_by,
                'posted_at' => $invoice->posted_at?->toISOString(),
            ];

            $invoice->posted_by = $actor->id;
            $invoice->posted_at = now();
            $invoice->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_invoice.post',
                table: 'vendor_invoices',
                recordId: (int) $invoice->id,
                oldValue: $old,
                newValue: [
                    'posted_by' => $invoice->posted_by,
                    'posted_at' => $invoice->posted_at?->toISOString(),
                    'journal_id' => $posted->id,
                ],
            );

            return $posted;
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

    private function findPostingAccount(int $companyId, string $code): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->first();

        if ($account === null) {
            throw new \DomainException('Missing required Chart of Account ' . $code . ' for company.');
        }

        if (!$account->is_postable) {
            throw new \DomainException('Chart of Account ' . $code . ' is not postable.');
        }

        return $account;
    }

    private function baseAmount(float $amount, float $exchangeRate): string
    {
        $value = $amount * ($exchangeRate ?: 1.0);
        return number_format(round($value, 2), 2, '.', '');
    }

    private function journalNumber(VendorInvoice $invoice): string
    {
        $base = 'APVI-' . $invoice->company_id . '-' . $invoice->id;

        $exists = Journal::query()
            ->where('company_id', $invoice->company_id)
            ->where('journal_number', $base)
            ->exists();

        if (!$exists) {
            return $base;
        }

        return $base . '-' . now()->format('His');
    }
}
