<?php

namespace App\Services\Accounting\Payments;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\User;
use App\Models\VendorPayment;
use App\Services\Accounting\Journal\JournalService;
use App\Services\Accounting\Journal\PostJournalService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PostVendorPaymentService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PostJournalService $postJournalService,
        private readonly ApproveVendorPaymentService $approveVendorPaymentService,
        private readonly PaymentAccountResolver $paymentAccountResolver,
    ) {
    }

    /**
     * Post a vendor payment:
     * - Requires payment to be approved (unless autoApproveIfNeeded=true)
     * - Requires allocations sum == payment amount
     * - Creates a balanced journal draft sourced from the payment
     * - Posts the journal
     * - Marks payment as posted (posted_by/posted_at)
     */
    public function post(VendorPayment $payment, User $actor, bool $autoApproveIfNeeded = false): Journal
    {
        return DB::transaction(function () use ($payment, $actor, $autoApproveIfNeeded): Journal {
            $payment->refresh();
            $payment->loadMissing(['allocations']);

            if ($payment->posted_at !== null) {
                throw new \DomainException('Vendor payment is already posted.');
            }

            if ($payment->status !== 'approved') {
                if ($payment->status === 'draft' && $autoApproveIfNeeded) {
                    $this->approveVendorPaymentService->approve($payment, $actor);
                    $payment->refresh();
                    $payment->loadMissing(['allocations']);
                } else {
                    throw new \DomainException('Vendor payment must be approved before posting.');
                }
            }

            $period = $this->findOpenPeriodForDate($payment->company_id, $payment->payment_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $this->assertAllocationsCoverPayment($payment);

            $cashOrBank = $this->paymentAccountResolver->resolveCashOrBankAccount(
                companyId: (int) $payment->company_id,
                method: (string) $payment->payment_method,
            );

            $apAccount = $this->findPostingAccount((int) $payment->company_id, '2-1100');

            $baseAmount = $this->baseAmount((float) $payment->amount, (float) $payment->exchange_rate);

            $journal = $this->journalService->createDraft(
                actor: $actor,
                journalAttributes: [
                    'journal_number' => $this->journalNumber($payment),
                    'company_id' => $payment->company_id,
                    'period_id' => $period->id,
                    'journal_date' => $payment->payment_date,
                    'source_type' => 'ap.vendor_payment',
                    'source_id' => $payment->id,
                    'description' => 'Vendor payment ' . $payment->payment_number,
                    'created_by' => $actor->id,
                ],
                linesAttributes: [
                    ['account_id' => $apAccount->id, 'debit' => $baseAmount, 'credit' => 0],
                    ['account_id' => $cashOrBank->id, 'debit' => 0, 'credit' => $baseAmount],
                ],
            );

            $posted = $this->postJournalService->post($journal, $actor);

            $old = [
                'posted_by' => $payment->posted_by,
                'posted_at' => $payment->posted_at?->toISOString(),
            ];

            $payment->posted_by = $actor->id;
            $payment->posted_at = now();
            $payment->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'vendor_payment.post',
                table: 'vendor_payments',
                recordId: (int) $payment->id,
                oldValue: $old,
                newValue: [
                    'posted_by' => $payment->posted_by,
                    'posted_at' => $payment->posted_at?->toISOString(),
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

    private function assertAllocationsCoverPayment(VendorPayment $payment): void
    {
        $paymentAmount = round((float) $payment->amount, 2);
        $allocated = round((float) $payment->allocations->sum('allocated_amount'), 2);

        if ($allocated <= 0) {
            throw new \DomainException('Vendor payment must have allocations before posting.');
        }

        if ($allocated !== $paymentAmount) {
            throw new \DomainException('Allocated amount must equal payment amount.');
        }
    }

    private function baseAmount(float $amount, float $exchangeRate): string
    {
        $value = $amount * ($exchangeRate ?: 1.0);
        return number_format(round($value, 2), 2, '.', '');
    }

    private function journalNumber(VendorPayment $payment): string
    {
        $base = 'APVP-' . $payment->company_id . '-' . $payment->id;

        $exists = Journal::query()
            ->where('company_id', $payment->company_id)
            ->where('journal_number', $base)
            ->exists();

        if (!$exists) {
            return $base;
        }

        return $base . '-' . now()->format('His');
    }
}
