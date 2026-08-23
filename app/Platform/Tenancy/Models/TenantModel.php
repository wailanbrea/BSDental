<?php

namespace App\Platform\Tenancy\Models;

use App\Platform\Tenancy\Exceptions\NoCurrentTenantException;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('tenant_context_enforced', function () {
            $context = app(TenantContext::class);
            if (! $context->check()) {
                throw NoCurrentTenantException::make();
            }
        });
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): string
    {
        return 'tenant';
    }
}
