<?php

namespace App\Services\Accounting\Inventory;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\InventoryMovement;
use App\Models\Journal;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Accounting\Journal\JournalService;
use App\Services\Accounting\Journal\PostJournalService;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class PostCogsJournalForSalesInvoiceService
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly PostJournalService $postJournalService,
    ) {
    }

    public function postIfNeeded(SalesInvoice $invoice, User $actor): ?Journal
    {
        return DB::transaction(function () use ($invoice, $actor): ?Journal {
            $invoice->loadMissing(['lines']);

            $productIds = $invoice->lines
                ->pluck('product_id')
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (count($productIds) === 0) {
                return null;
            }

            $stockProductIds = Product::query()
                ->where('company_id', $invoice->company_id)
                ->whereIn('id', $productIds)
                ->where('type', 'stock_item')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($stockProductIds) === 0) {
                return null;
            }

            $period = $this->findOpenPeriodForDate($invoice->company_id, $invoice->invoice_date);
            if ($period === null) {
                throw new \DomainException('Accounting period is closed.');
            }

            $existing = Journal::query()
                ->where('company_id', $invoice->company_id)
                ->where('source_type', 'inventory.cogs')
                ->where('source_id', $invoice->id)
                ->first();

            if ($existing !== null) {
                throw new \DomainException('COGS journal already exists for this sales invoice.');
            }

            $outMovementIds = InventoryMovement::query()
                ->where('company_id', $invoice->company_id)
                ->where('type', 'out')
                ->whereNotNull('posted_at')
                ->whereNull('deleted_at')
                ->where('reference_id', $invoice->id)
                ->whereIn('reference_type', ['ar.sales_invoice', 'sales_invoice'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (count($outMovementIds) === 0) {
                throw new \DomainException('Missing posted inventory OUT movement for this sales invoice.');
            }

            $costByProduct = DB::table('inventory_movement_lines')
                ->select('product_id', DB::raw('SUM(COALESCE(valued_total_cost, 0)) as total_cost'))
                ->whereIn('inventory_movement_id', $outMovementIds)
                ->whereIn('product_id', $stockProductIds)
                ->groupBy('product_id')
                ->get();

            $totalCogs = 0.0;
            $seenProductIds = [];
            foreach ($costByProduct as $row) {
                $seenProductIds[] = (int) $row->product_id;
                $totalCogs += (float) $row->total_cost;
            }

            $missing = array_values(array_diff($stockProductIds, $seenProductIds));
            if (count($missing) > 0) {
                throw new \DomainException('Missing valued inventory OUT lines for some products in this sales invoice.');
            }

            if ($totalCogs <= 0) {
                throw new \DomainException('COGS total must be greater than 0.');
            }

            $cogsAccount = $this->findPostingAccount($invoice->company_id, '5-1100');
            $inventoryAccount = $this->findPostingAccount($invoice->company_id, '1-1400');

            $amount = number_format(round($totalCogs, 2), 2, '.', '');

            $journal = $this->journalService->createDraft(
                actor: $actor,
                journalAttributes: [
                    'journal_number' => $this->journalNumber($invoice),
                    'company_id' => $invoice->company_id,
                    'period_id' => $period->id,
                    'journal_date' => $invoice->invoice_date,
                    'source_type' => 'inventory.cogs',
                    'source_id' => $invoice->id,
                    'description' => 'COGS for Sales invoice ' . $invoice->invoice_number,
                    'created_by' => $actor->id,
                ],
                linesAttributes: [
                    ['account_id' => $cogsAccount->id, 'debit' => $amount, 'credit' => 0],
                    ['account_id' => $inventoryAccount->id, 'debit' => 0, 'credit' => $amount],
                ],
            );

            $posted = $this->postJournalService->post($journal, $actor);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'sales_invoice.post_cogs',
                table: 'sales_invoices',
                recordId: (int) $invoice->id,
                oldValue: null,
                newValue: [
                    'cogs_journal_id' => (int) $posted->id,
                    'cogs_amount' => $amount,
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

    private function journalNumber(SalesInvoice $invoice): string
    {
        return 'COGS-' . $invoice->company_id . '-' . $invoice->id;
    }
}
