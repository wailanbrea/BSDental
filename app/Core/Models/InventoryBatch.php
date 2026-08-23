<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $inventory_item_id
 * @property string $warehouse_id
 * @property string $batch_number
 * @property float $initial_quantity
 * @property float $current_quantity
 * @property float $cost_per_unit
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property InventoryItem $item
 * @property Warehouse $warehouse
 */
class InventoryBatch extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'inventory_batches';

    protected $fillable = [
        'id',
        'inventory_item_id',
        'warehouse_id',
        'batch_number',
        'initial_quantity',
        'current_quantity',
        'cost_per_unit',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'initial_quantity' => 'float',
            'current_quantity' => 'float',
            'cost_per_unit' => 'float',
            'expires_at' => 'date',
        ];
    }

    /**
     * Item relation.
     *
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Warehouse relation.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
