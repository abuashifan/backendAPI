<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\User;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     */
    public function log(
        ?User $actor,
        string $action,
        string $table,
        int $recordId,
        ?array $oldValue = null,
        ?array $newValue = null,
    ): AuditEvent {
        return AuditEvent::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'table' => $table,
            'record_id' => $recordId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'performed_at' => now(),
        ]);
    }
}
