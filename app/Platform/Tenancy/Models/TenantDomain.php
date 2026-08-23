<?php

namespace App\Platform\Tenancy\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $domain
 * @property bool $is_primary
 * @property bool $is_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantDomain extends Model
{
    use HasUuids;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_domains';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'is_verified',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * The tenant that owns the domain.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
