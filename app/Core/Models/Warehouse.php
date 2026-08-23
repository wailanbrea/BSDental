<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $branch_id
 * @property string $name
 * @property bool $is_main
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Branch $branch
 */
class Warehouse extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'warehouses';

    protected $fillable = [
        'id',
        'branch_id',
        'name',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
        ];
    }

    /**
     * Branch relation.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
