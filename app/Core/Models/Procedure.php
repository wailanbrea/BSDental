<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $category_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property int $estimated_minutes
 * @property float $tax_rate
 * @property bool $requires_lab
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property ProcedureCategory $category
 */
class Procedure extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'procedures';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'category_id',
        'code',
        'name',
        'description',
        'price',
        'estimated_minutes',
        'tax_rate',
        'requires_lab',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'estimated_minutes' => 'integer',
            'tax_rate' => 'float',
            'requires_lab' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Category relation.
     *
     * @return BelongsTo<ProcedureCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProcedureCategory::class, 'category_id');
    }
}
