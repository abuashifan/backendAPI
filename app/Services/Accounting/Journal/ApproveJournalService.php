<?php

namespace App\Services\Accounting\Journal;

use App\Models\AccountingPeriod;
use App\Models\Journal;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class ApproveJournalService
{
    public function approve(Journal $journal, User $actor, ?string $note = null): Journal
    {
        return DB::transaction(function () use ($journal, $actor): Journal {
            $journal->refresh();
            $journal->loadMissing(['period']);

            $old = [
                'status' => $journal->status,
                'approved_by' => $journal->approved_by,
                'approved_at' => $journal->approved_at?->toISOString(),
            ];

            if ($journal->status !== 'draft') {
                throw new \DomainException('Only draft journals can be approved.');
            }

            /** @var AccountingPeriod|null $period */
            $period = $journal->period;
            if ($period === null || $period->status !== 'open') {
                throw new \DomainException('Accounting period is closed.');
            }

            $journal->status = 'approved';
            $journal->approved_by = $actor->id;
            $journal->approved_at = now();
            $journal->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'journal.approve',
                table: 'journals',
                recordId: (int) $journal->id,
                oldValue: $old,
                newValue: [
                    'status' => $journal->status,
                    'approved_by' => $journal->approved_by,
                    'approved_at' => $journal->approved_at?->toISOString(),
                ],
            );

            return $journal;
        });
    }
}
