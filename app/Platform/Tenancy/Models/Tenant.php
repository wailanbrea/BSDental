<?php

namespace App\Platform\Tenancy\Models;

use App\Platform\Plans\Models\Plan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Models\Concerns\ImplementsTenant;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $database_name
 * @property string|null $database_host
 * @property string|null $database_username
 * @property string|null $database_password
 * @property string $status
 * @property string|null $plan_id
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Tenant extends Model implements IsTenant
{
    use HasUuids, ImplementsTenant, SoftDeletes, UsesLandlordConnection;

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
    protected $table = 'tenants';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'database_name',
        'database_host',
        'database_username',
        'database_password',
        'status',
        'plan_id',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'database_password' => 'encrypted',
        ];
    }

    /**
     * Domains associated with this tenant.
     *
     * @return HasMany<TenantDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class, 'tenant_id');
    }

    /**
     * Primary domain for this tenant.
     *
     * @return HasOne<TenantDomain, $this>
     */
    public function primaryDomain(): HasOne
    {
        return $this->hasOne(TenantDomain::class, 'tenant_id')->where('is_primary', true);
    }

    /**
     * Commercial plan for this tenant.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * Determine if tenant has entitlement for a specific module.
     */
    public function hasModuleEntitlement(string $module): bool
    {
        // 1. Check custom overrides in settings
        /** @var array<string, bool>|null $overrides */
        $overrides = $this->settings['module_overrides'] ?? null;
        if (is_array($overrides) && array_key_exists($module, $overrides)) {
            return (bool) $overrides[$module];
        }

        // 2. Check commercial plan
        if ($this->relationLoaded('plan') && $this->plan instanceof Plan) {
            return $this->plan->hasModule($module);
        }

        if ($this->plan_id !== null) {
            /** @var Plan|null $plan */
            $plan = Plan::find($this->plan_id);

            return $plan?->hasModule($module) ?? false;
        }

        return false;
    }

    /**
     * Determine if tenant is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine if tenant is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Determine if tenant is currently provisioning.
     */
    public function isProvisioning(): bool
    {
        return $this->status === 'provisioning';
    }

    /**
     * Get database name for this tenant.
     */
    public function getDatabaseName(): string
    {
        return $this->database_name;
    }

    /**
     * Get unique tenant key/UUID.
     */
    public function getTenantKey(): string
    {
        return $this->id;
    }
}
