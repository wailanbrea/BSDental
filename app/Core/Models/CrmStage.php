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
 * @property string $slug
 * @property string $color
 * @property int $order_index
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int, PatientCrmProfile> $profiles
 */
class CrmStage extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'crm_stages';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'color',
        'order_index',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Profiles relation.
     *
     * @return HasMany<PatientCrmProfile, $this>
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(PatientCrmProfile::class, 'stage_id');
    }
}
