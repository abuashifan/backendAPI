<?php

namespace App\Services\Accounting\Period;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class OpenPeriodService
{
    public function open(AccountingPeriod $period, User $actor): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $actor): AccountingPeriod {
            $period->refresh();

            $old = [
                'status' => $period->status,
                'closed_at' => $period->closed_at?->toISOString(),
            ];

            if ($period->status === 'open') {
                return $period;
            }

            $period->status = 'open';
            $period->closed_at = null;
            $period->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'period.open',
                table: 'accounting_periods',
                recordId: (int) $period->id,
                oldValue: $old,
                newValue: [
                    'status' => $period->status,
                    'closed_at' => $period->closed_at,
                ],
            );

            return $period;
        });
    }
}
