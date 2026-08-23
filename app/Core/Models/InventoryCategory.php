<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, InventoryItem> $items
 */
class InventoryCategory extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'inventory_categories';

    protected $fillable = [
        'id',
        'name',
        'description',
    ];

    /**
     * Items relation.
     *
     * @return HasMany<InventoryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'category_id');
    }
}
