<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $category_id
 * @property string|null $sku
 * @property string $name
 * @property string $unit
 * @property float $min_stock
 * @property float $cost_price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property InventoryCategory $category
 * @property Collection<int, InventoryBatch> $batches
 * @property Collection<int, StockMovement> $movements
 */
class InventoryItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'inventory_items';

    protected $fillable = [
        'id',
        'category_id',
        'sku',
        'name',
        'unit',
        'min_stock',
        'cost_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock' => 'float',
            'cost_price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Category relation.
     *
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    /**
     * Batches relation.
     *
     * @return HasMany<InventoryBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'inventory_item_id');
    }

    /**
     * Movements relation.
     *
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'inventory_item_id');
    }

    public function totalStock(): float
    {
        return (float) $this->batches()->sum('current_quantity');
    }
}
