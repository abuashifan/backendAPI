<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostAllocation extends Model
{
    protected $fillable = [
        'out_movement_line_id',
        'inventory_cost_layer_id',
        'qty',
        'unit_cost',
        'total_cost',
    ];

    protected $casts = [
        'out_movement_line_id' => 'integer',
        'inventory_cost_layer_id' => 'integer',
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
    ];

    public function outMovementLine(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementLine::class, 'out_movement_line_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(InventoryCostLayer::class, 'inventory_cost_layer_id');
    }
}
