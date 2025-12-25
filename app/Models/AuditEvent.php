<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'table',
        'record_id',
        'old_value',
        'new_value',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'record_id' => 'integer',
            'old_value' => 'array',
            'new_value' => 'array',
            'performed_at' => 'datetime',
        ];
    }
}
