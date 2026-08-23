<?php

namespace App\Platform\Tenancy\Facades;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Tenant|null current()
 * @method static bool check()
 * @method static Tenant requireCurrent()
 * @method static void makeCurrent(Tenant $tenant)
 * @method static void forgetCurrent()
 * @method static mixed execute(Tenant $tenant, callable $callback)
 *
 * @see TenantContext
 */
class TenantContextFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantContext::class;
    }
}
