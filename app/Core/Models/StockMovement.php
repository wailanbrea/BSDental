<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $inventory_item_id
 * @property string $warehouse_id
 * @property string|null $batch_id
 * @property string $type
 * @property float $quantity
 * @property float $previous_stock
 * @property float $new_stock
 * @property float $unit_cost
 * @property float $total_cost
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $notes
 * @property string|null $created_by_user_id
 * @property Carbon $created_at
 * @property InventoryItem $item
 * @property Warehouse $warehouse
 * @property InventoryBatch|null $batch
 * @property User|null $createdBy
 */
class StockMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'stock_movements';

    protected $fillable = [
        'id',
        'inventory_item_id',
        'warehouse_id',
        'batch_id',
        'type',
        'quantity',
        'previous_stock',
        'new_stock',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'previous_stock' => 'float',
            'new_stock' => 'float',
            'unit_cost' => 'float',
            'total_cost' => 'float',
            'created_at' => 'datetime',
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

    /**
     * Batch relation.
     *
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    /**
     * User relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
