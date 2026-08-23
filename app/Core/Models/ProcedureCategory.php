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
 * @property string $color
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, Procedure> $procedures
 */
class ProcedureCategory extends Model
{
    use HasUuids;

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
    protected $table = 'procedure_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'color',
    ];

    /**
     * Procedures relation.
     *
     * @return HasMany<Procedure, $this>
     */
    public function procedures(): HasMany
    {
        return $this->hasMany(Procedure::class, 'category_id');
    }
}
