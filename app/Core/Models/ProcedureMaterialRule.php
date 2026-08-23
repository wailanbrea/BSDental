<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $procedure_id
 * @property string $inventory_item_id
 * @property float $quantity_required
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Procedure $procedure
 * @property InventoryItem $item
 */
class ProcedureMaterialRule extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'procedure_material_rules';

    protected $fillable = [
        'id',
        'procedure_id',
        'inventory_item_id',
        'quantity_required',
    ];

    protected function casts(): array
    {
        return [
            'quantity_required' => 'float',
        ];
    }

    /**
     * Procedure relation.
     *
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
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
}
