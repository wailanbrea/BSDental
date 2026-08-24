<?php

namespace App\Core\Services;

use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryItem;
use App\Core\Models\ProcedureMaterialRule;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryStockService
{
    /**
     * Record a purchase entry with a batch/lot in a warehouse.
     */
    public function recordPurchase(
        InventoryItem $item,
        Warehouse $warehouse,
        string $batchNumber,
        float $quantity,
        float $costPerUnit,
        ?string $expiresAt = null,
        ?string $userId = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad ingresada debe ser mayor a cero.');
        }

        return DB::connection('tenant')->transaction(function () use ($item, $warehouse, $batchNumber, $quantity, $costPerUnit, $expiresAt, $userId) {
            $prevStock = (float) InventoryBatch::where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->sum('current_quantity');

            $batch = InventoryBatch::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'batch_number' => $batchNumber,
                'initial_quantity' => $quantity,
                'current_quantity' => $quantity,
                'cost_per_unit' => $costPerUnit,
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt) : null,
            ]);

            $newStock = $prevStock + $quantity;

            return StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'batch_id' => $batch->id,
                'type' => 'purchase_in',
                'quantity' => $quantity,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'unit_cost' => $costPerUnit,
                'total_cost' => $quantity * $costPerUnit,
                'reference_type' => 'Purchase',
                'reference_id' => $batch->id,
                'notes' => "Ingreso por compra Lote: {$batchNumber}",
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Record an auditable manual inventory adjustment (INV-02).
     */
    public function recordManualAdjustment(
        InventoryItem $item,
        Warehouse $warehouse,
        string $type,
        float $quantity,
        string $reason,
        ?InventoryBatch $batch = null,
        ?string $userId = null
    ): StockMovement {
        if (! in_array($type, ['adjustment_in', 'adjustment_out', 'waste_loss'], true)) {
            throw new InvalidArgumentException("Tipo de ajuste de inventario no válido: {$type}");
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('La cantidad del ajuste debe ser mayor a cero.');
        }

        if (strlen(trim($reason)) < 5) {
            throw new InvalidArgumentException('El motivo del ajuste es obligatorio (mínimo 5 caracteres).');
        }

        return DB::connection('tenant')->transaction(function () use ($item, $warehouse, $type, $quantity, $reason, $batch, $userId) {
            $prevStock = (float) InventoryBatch::where('inventory_item_id', $item->id)
                ->where('warehouse_id', $warehouse->id)
                ->sum('current_quantity');

            $unitCost = $batch ? $batch->cost_per_unit : (float) $item->cost_price;

            if ($type === 'adjustment_in') {
                // If specific batch provided, increment it; otherwise create adjustment batch
                if ($batch) {
                    $batch->update(['current_quantity' => $batch->current_quantity + $quantity]);
                    $targetBatchId = $batch->id;
                } else {
                    $newBatch = InventoryBatch::create([
                        'inventory_item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'batch_number' => 'ADJ-'.now()->format('YmdHis'),
                        'initial_quantity' => $quantity,
                        'current_quantity' => $quantity,
                        'cost_per_unit' => $unitCost,
                    ]);
                    $targetBatchId = $newBatch->id;
                }

                $signedQty = $quantity;
                $newStock = $prevStock + $quantity;
            } else {
                // Outflow: adjustment_out or waste_loss
                if ($prevStock < $quantity) {
                    throw new InvalidArgumentException('Stock insuficiente en la bodega para procesar este egreso.');
                }

                if ($batch) {
                    if ($batch->current_quantity < $quantity) {
                        throw new InvalidArgumentException('El lote seleccionado no cuenta con suficiente stock.');
                    }
                    $batch->update(['current_quantity' => $batch->current_quantity - $quantity]);
                    $targetBatchId = $batch->id;
                } else {
                    // Deduct FIFO
                    $qtyToDeduct = $quantity;
                    $batches = InventoryBatch::where('inventory_item_id', $item->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('current_quantity', '>', 0)
                        ->orderByRaw('expires_at IS NULL, expires_at ASC')
                        ->get();

                    $targetBatchId = $batches->first()?->id;

                    foreach ($batches as $b) {
                        if ($qtyToDeduct <= 0) {
                            break;
                        }
                        $take = min($qtyToDeduct, $b->current_quantity);
                        $b->update(['current_quantity' => $b->current_quantity - $take]);
                        $qtyToDeduct -= $take;
                    }
                }

                $signedQty = -$quantity;
                $newStock = $prevStock - $quantity;
            }

            return StockMovement::create([
                'inventory_item_id' => $item->id,
                'warehouse_id' => $warehouse->id,
                'batch_id' => $targetBatchId,
                'type' => $type,
                'quantity' => $signedQty,
                'previous_stock' => $prevStock,
                'new_stock' => $newStock,
                'unit_cost' => $unitCost,
                'total_cost' => abs($signedQty) * $unitCost,
                'reference_type' => 'ManualAdjustment',
                'notes' => $reason,
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Consume procedure materials idempotently using FIFO / earliest expiry first.
     *
     * @return list<StockMovement>
     */
    public function consumeMaterialsForProcedure(
        TreatmentPlanItem $planItem,
        Warehouse $warehouse,
        ?string $userId = null
    ): array {
        $existingMovements = StockMovement::where('reference_type', 'TreatmentPlanItem')
            ->where('reference_id', $planItem->id)
            ->get()
            ->all();

        if (count($existingMovements) > 0) {
            return $existingMovements;
        }

        $rules = ProcedureMaterialRule::where('procedure_id', $planItem->procedure_id)->get();
        if ($rules->isEmpty()) {
            return [];
        }

        return DB::connection('tenant')->transaction(function () use ($planItem, $warehouse, $userId, $rules) {
            $movements = [];

            foreach ($rules as $rule) {
                $qtyNeeded = $rule->quantity_required;
                $item = $rule->item;

                $batches = InventoryBatch::where('inventory_item_id', $item->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('current_quantity', '>', 0)
                    ->orderByRaw('expires_at IS NULL, expires_at ASC')
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($batches as $batch) {
                    if ($qtyNeeded <= 0) {
                        break;
                    }

                    $prevStock = (float) InventoryBatch::where('inventory_item_id', $item->id)
                        ->where('warehouse_id', $warehouse->id)
                        ->sum('current_quantity');

                    $deduct = min($qtyNeeded, $batch->current_quantity);
                    $batch->update(['current_quantity' => $batch->current_quantity - $deduct]);
                    $qtyNeeded -= $deduct;

                    $newStock = $prevStock - $deduct;

                    $mov = StockMovement::create([
                        'inventory_item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'batch_id' => $batch->id,
                        'type' => 'procedure_consumption',
                        'quantity' => -$deduct,
                        'previous_stock' => $prevStock,
                        'new_stock' => $newStock,
                        'unit_cost' => $batch->cost_per_unit,
                        'total_cost' => $deduct * $batch->cost_per_unit,
                        'reference_type' => 'TreatmentPlanItem',
                        'reference_id' => $planItem->id,
                        'notes' => "Consumo en procedimiento: {$planItem->procedure->name}",
                        'created_by_user_id' => $userId,
                        'created_at' => now(),
                    ]);

                    $movements[] = $mov;
                }
            }

            return $movements;
        });
    }

    /**
     * Get item Kardex movement history (INV-01).
     */
    public function getItemKardex(InventoryItem $item, ?Warehouse $warehouse = null): array
    {
        $query = StockMovement::with(['warehouse', 'batch', 'createdBy'])
            ->where('inventory_item_id', $item->id);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $movements = $query->orderBy('created_at', 'desc')->get();
        $totalStock = (float) InventoryBatch::where('inventory_item_id', $item->id)->sum('current_quantity');

        return [
            'item' => $item,
            'total_stock' => $totalStock,
            'movements' => $movements,
        ];
    }

    /**
     * Get inventory alerts (low stock & expiring batches) (INV-01).
     */
    public function getInventoryAlerts(?Warehouse $warehouse = null): array
    {
        $now = Carbon::now();
        $soonThreshold = Carbon::now()->addDays(30);

        // Low stock items
        $items = InventoryItem::where('is_active', true)->get();
        $lowStockItems = [];

        foreach ($items as $item) {
            $batchQuery = InventoryBatch::where('inventory_item_id', $item->id);
            if ($warehouse) {
                $batchQuery->where('warehouse_id', $warehouse->id);
            }
            $stock = (float) $batchQuery->sum('current_quantity');

            if ($stock <= (float) $item->min_stock) {
                $lowStockItems[] = [
                    'item' => $item,
                    'current_stock' => $stock,
                    'min_stock' => (float) $item->min_stock,
                    'unit' => $item->unit,
                ];
            }
        }

        // Expiring batches (< 30 days)
        $batchQuery = InventoryBatch::with(['item', 'warehouse'])
            ->where('current_quantity', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $soonThreshold);

        if ($warehouse) {
            $batchQuery->where('warehouse_id', $warehouse->id);
        }

        $expiringBatches = $batchQuery->orderBy('expires_at')->get();

        return [
            'low_stock' => $lowStockItems,
            'expiring_batches' => $expiringBatches,
            'low_stock_count' => count($lowStockItems),
            'expiring_count' => $expiringBatches->count(),
        ];
    }
}
