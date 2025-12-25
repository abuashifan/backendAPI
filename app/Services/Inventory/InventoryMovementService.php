<?php

namespace App\Services\Inventory;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementLine;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /**
     * @param  array<string, mixed>  $movementAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function createDraft(array $movementAttributes, array $linesAttributes, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($movementAttributes, $linesAttributes, $actor): InventoryMovement {
            if (count($linesAttributes) < 1) {
                throw new \DomainException('Inventory movement must have at least one line.');
            }

            $warehouse = Warehouse::query()->findOrFail((int) $movementAttributes['warehouse_id']);
            if ((int) $warehouse->company_id !== (int) $movementAttributes['company_id']) {
                throw new \DomainException('Warehouse does not belong to company.');
            }

            $movement = InventoryMovement::query()->create([
                'company_id' => $movementAttributes['company_id'],
                'warehouse_id' => $movementAttributes['warehouse_id'],
                'movement_number' => $movementAttributes['movement_number'],
                'movement_date' => $movementAttributes['movement_date'],
                'type' => $movementAttributes['type'],
                'status' => 'draft',
                'reference_type' => $movementAttributes['reference_type'] ?? null,
                'reference_id' => $movementAttributes['reference_id'] ?? null,
                'notes' => $movementAttributes['notes'] ?? null,
                'created_by' => $actor->id,
                'posted_by' => null,
                'posted_at' => null,
            ]);

            $this->replaceLines($movement, $linesAttributes);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'inventory_movement.create',
                table: 'inventory_movements',
                recordId: (int) $movement->id,
                oldValue: null,
                newValue: [
                    'status' => $movement->status,
                    'type' => $movement->type,
                    'warehouse_id' => $movement->warehouse_id,
                    'movement_date' => $movement->movement_date?->format('Y-m-d'),
                ],
            );

            return $movement;
        });
    }

    /**
     * @param  array<string, mixed>  $movementAttributes
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    public function updateDraft(InventoryMovement $movement, array $movementAttributes, array $linesAttributes, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $movementAttributes, $linesAttributes, $actor): InventoryMovement {
            $movement->refresh();

            if ($movement->posted_at !== null) {
                throw new \DomainException('Posted inventory movements cannot be edited.');
            }

            if ($movement->status !== 'draft') {
                throw new \DomainException('Only draft inventory movements can be edited.');
            }

            if (count($linesAttributes) < 1) {
                throw new \DomainException('Inventory movement must have at least one line.');
            }

            $old = [
                'movement_number' => $movement->movement_number,
                'movement_date' => $movement->movement_date?->format('Y-m-d'),
                'type' => $movement->type,
                'warehouse_id' => $movement->warehouse_id,
                'notes' => $movement->notes,
            ];

            if (array_key_exists('warehouse_id', $movementAttributes)) {
                $warehouse = Warehouse::query()->findOrFail((int) $movementAttributes['warehouse_id']);
                if ((int) $warehouse->company_id !== (int) $movement->company_id) {
                    throw new \DomainException('Warehouse does not belong to company.');
                }
            }

            $movement->fill([
                'warehouse_id' => $movementAttributes['warehouse_id'] ?? $movement->warehouse_id,
                'movement_number' => $movementAttributes['movement_number'] ?? $movement->movement_number,
                'movement_date' => $movementAttributes['movement_date'] ?? $movement->movement_date,
                'type' => $movementAttributes['type'] ?? $movement->type,
                'reference_type' => $movementAttributes['reference_type'] ?? $movement->reference_type,
                'reference_id' => $movementAttributes['reference_id'] ?? $movement->reference_id,
                'notes' => $movementAttributes['notes'] ?? $movement->notes,
            ]);
            $movement->save();

            $this->replaceLines($movement, $linesAttributes);

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'inventory_movement.update',
                table: 'inventory_movements',
                recordId: (int) $movement->id,
                oldValue: $old,
                newValue: [
                    'movement_number' => $movement->movement_number,
                    'movement_date' => $movement->movement_date?->format('Y-m-d'),
                    'type' => $movement->type,
                    'warehouse_id' => $movement->warehouse_id,
                    'notes' => $movement->notes,
                ],
            );

            return $movement;
        });
    }

    public function deleteDraft(InventoryMovement $movement, User $actor): void
    {
        DB::transaction(function () use ($movement, $actor): void {
            $movement->refresh();

            if ($movement->posted_at !== null) {
                throw new \DomainException('Posted inventory movements cannot be deleted.');
            }

            if ($movement->status !== 'draft') {
                throw new \DomainException('Only draft inventory movements can be deleted.');
            }

            $movement->delete();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'inventory_movement.delete',
                table: 'inventory_movements',
                recordId: (int) $movement->id,
                oldValue: ['status' => 'draft'],
                newValue: null,
            );
        });
    }

    public function post(InventoryMovement $movement, User $actor): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $actor): InventoryMovement {
            $movement->refresh();
            $movement->loadMissing(['lines']);

            if ($movement->posted_at !== null) {
                throw new \DomainException('Inventory movement is already posted.');
            }

            if ($movement->status !== 'draft') {
                throw new \DomainException('Only draft inventory movements can be posted.');
            }

            if ($movement->lines->count() < 1) {
                throw new \DomainException('Inventory movement must have at least one line.');
            }

            $this->assertProductsAreStockItemsAndCompanyScoped($movement);

            if ($movement->type === 'out') {
                foreach ($movement->lines as $line) {
                    $qty = (float) $line->qty;
                    $onHand = $this->getOnHandQty(
                        companyId: (int) $movement->company_id,
                        warehouseId: (int) $movement->warehouse_id,
                        productId: (int) $line->product_id,
                    );

                    if ($qty > $onHand) {
                        throw new \DomainException('Insufficient stock for product_id '.$line->product_id.'.');
                    }
                }
            }

            $movement->status = 'posted';
            $movement->posted_by = $actor->id;
            $movement->posted_at = now();
            $movement->save();

            app(AuditLogger::class)->log(
                actor: $actor,
                action: 'inventory_movement.post',
                table: 'inventory_movements',
                recordId: (int) $movement->id,
                oldValue: ['status' => 'draft'],
                newValue: ['status' => 'posted'],
            );

            return $movement;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesAttributes
     */
    private function replaceLines(InventoryMovement $movement, array $linesAttributes): void
    {
        InventoryMovementLine::query()
            ->where('inventory_movement_id', $movement->id)
            ->delete();

        foreach ($linesAttributes as $line) {
            $qty = (float) ($line['qty'] ?? 0);
            if ($qty <= 0) {
                throw new \DomainException('Line qty must be greater than 0.');
            }

            $product = Product::query()->findOrFail((int) $line['product_id']);
            if ((int) $product->company_id !== (int) $movement->company_id) {
                throw new \DomainException('Product does not belong to company.');
            }
            if ($product->type !== 'stock_item') {
                throw new \DomainException('Only stock_item products can be used in inventory movements.');
            }

            InventoryMovementLine::query()->create([
                'inventory_movement_id' => $movement->id,
                'product_id' => $line['product_id'],
                'qty' => number_format($qty, 2, '.', ''),
                'description' => $line['description'] ?? null,
            ]);
        }
    }

    private function assertProductsAreStockItemsAndCompanyScoped(InventoryMovement $movement): void
    {
        foreach ($movement->lines as $line) {
            $product = Product::query()->findOrFail((int) $line->product_id);

            if ((int) $product->company_id !== (int) $movement->company_id) {
                throw new \DomainException('Product does not belong to company.');
            }

            if ($product->type !== 'stock_item') {
                throw new \DomainException('Only stock_item products can be used in inventory movements.');
            }
        }
    }

    private function getOnHandQty(int $companyId, int $warehouseId, int $productId): float
    {
        $inQty = (float) InventoryMovementLine::query()
            ->where('product_id', $productId)
            ->whereHas('movement', function ($query) use ($companyId, $warehouseId): void {
                $query
                    ->whereNull('deleted_at')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'in')
                    ->whereNotNull('posted_at');
            })
            ->sum('qty');

        $outQty = (float) InventoryMovementLine::query()
            ->where('product_id', $productId)
            ->whereHas('movement', function ($query) use ($companyId, $warehouseId): void {
                $query
                    ->whereNull('deleted_at')
                    ->where('company_id', $companyId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'out')
                    ->whereNotNull('posted_at');
            })
            ->sum('qty');

        return $inQty - $outQty;
    }
}
