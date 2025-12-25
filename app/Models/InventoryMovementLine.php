<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovementLine extends Model
{
    protected $fillable = [
        'inventory_movement_id',
        'product_id',
        'qty',
        'description',
        'unit_cost',
        'valued_unit_cost',
        'valued_total_cost',
    ];

    protected $casts = [
        'inventory_movement_id' => 'integer',
        'product_id' => 'integer',
        'qty' => 'decimal:2',
        'unit_cost' => 'decimal:6',
        'valued_unit_cost' => 'decimal:6',
        'valued_total_cost' => 'decimal:2',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
