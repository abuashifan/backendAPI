<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'movement_number',
        'movement_date',
        'type',
        'status',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'warehouse_id' => 'integer',
        'reference_id' => 'integer',
        'created_by' => 'integer',
        'posted_by' => 'integer',
        'movement_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryMovementLine::class);
    }
}
