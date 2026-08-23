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
     * Consume procedure materials idempotently using FIFO / earliest expiry first.
     *
     * @return list<StockMovement>
     */
    public function consumeMaterialsForProcedure(
        TreatmentPlanItem $planItem,
        Warehouse $warehouse,
        ?string $userId = null
    ): array {
        // Idempotency check: if materials already consumed for this plan item, return existing movements
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
}
