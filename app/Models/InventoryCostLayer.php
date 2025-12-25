<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCostLayer extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'product_id',
        'source_movement_line_id',
        'received_at',
        'unit_cost',
        'qty_received',
        'qty_remaining',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'source_movement_line_id' => 'integer',
        'received_at' => 'date',
        'unit_cost' => 'decimal:6',
        'qty_received' => 'decimal:2',
        'qty_remaining' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceMovementLine(): BelongsTo
    {
        return $this->belongsTo(InventoryMovementLine::class, 'source_movement_line_id');
    }
}
