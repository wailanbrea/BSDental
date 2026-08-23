<?php

namespace App\Platform\Security\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $platform_user_id
 * @property string|null $tenant_id
 * @property string $action
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 */
class LandlordAuditLog extends Model
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
    protected $table = 'audit_logs';

    /**
     * Indicates if model should be timestamped with updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'platform_user_id',
        'tenant_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
