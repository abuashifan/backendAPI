<?php

namespace App\Services\Accounting\AR;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Accounting\Journal\JournalService;
use App\Services\Accounting\Journal\PostJournalService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PostSalesInvoiceService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PostJournalService $postJournalService,
        private readonly ApproveSalesInvoiceService $approveSalesInvoiceService,
    ) {
    }

    /**
     * Post a sales invoice:
     * - Requires invoice to be approved (unless autoApproveIfNeeded=true)
     * - Creates a balanced journal draft sourced from the invoice
     * - Posts the journal
     * - Marks invoice as posted (posted_by/posted_at)
     */
    public function post(SalesInvoice $invoice, User $actor, bool $autoApproveIfNeeded = false): Journal
    {
        return DB::transaction(function () use ($invoice, $actor, $autoApproveIfNeeded): Journal {
            $invoice->refresh();

            if ($invoice->posted_at !== null) {
                throw new \DomainException('Sales invoice is already posted.');
            }

            if ($invoice->status !== 'approved') {
                if ($invoice->status === 'draft' && $autoApproveIfNeeded) {
                    $this->approveSalesInvoiceService->approve($invoice, $actor);
                    $invoice->refresh();
                } else {
                    throw new \DomainException('Sales invoice must be approved before posting.');
                }
            }

            $period = $this->findOpenPeriodForDate($invoice->company_id, $invoice->invoice_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $arAccount = $this->findPostingAccount($invoice->company_id, '1-1300');
            $salesRevenue = $this->findPostingAccount($invoice->company_id, '4-1100');

            $baseTotal = $this->baseAmount((float) $invoice->total_amount, (float) $invoice->exchange_rate);
            $baseSubtotal = $this->baseAmount((float) $invoice->subtotal, (float) $invoice->exchange_rate);
            $baseTax = $this->baseAmount((float) $invoice->tax_amount, (float) $invoice->exchange_rate);

            $lines = [
                ['account_id' => $arAccount->id, 'debit' => $baseTotal, 'credit' => 0],
                ['account_id' => $salesRevenue->id, 'debit' => 0, 'credit' => $baseSubtotal],
            ];

            if ((float) $invoice->tax_amount > 0) {
                $taxPayable = $this->findPostingAccount($invoice->company_id, '2-1200');
                $lines[] = ['account_id' => $taxPayable->id, 'debit' => 0, 'credit' => $baseTax];
            }

            $journalNumber = $this->journalNumber($invoice);

            $journal = $this->journalService->createDraft(
                actor: $actor,
                journalAttributes: [
                    'journal_number' => $journalNumber,
                    'company_id' => $invoice->company_id,
                    'period_id' => $period->id,
                    'journal_date' => $invoice->invoice_date,
                    'source_type' => 'ar.sales_invoice',
                    'source_id' => $invoice->id,
                    'description' => 'Sales invoice ' . $invoice->invoice_number,
                    'created_by' => $actor->id,
                ],
                linesAttributes: $lines,
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
                action: 'sales_invoice.post',
                table: 'sales_invoices',
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

    private function journalNumber(SalesInvoice $invoice): string
    {
        return 'ARSI-' . $invoice->company_id . '-' . $invoice->id;
    }
}
