<?php

namespace App\Platform\Tenancy\TenantFinder;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use Illuminate\Http\Request;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class DomainTenantFinder extends TenantFinder
{
    /**
     * Find tenant model for the given request by normalized domain host.
     */
    public function findTenant(Request $request): ?Tenant
    {
        $host = strtolower(trim($request->getHost()));

        /** @var TenantDomain|null $domain */
        $domain = TenantDomain::query()
            ->where('domain', $host)
            ->where('is_verified', true)
            ->with('tenant')
            ->first();

        return $domain?->tenant;
    }

    /**
     * Find tenant for the given request by normalized domain host (Spatie contract).
     */
    public function findForRequest(Request $request): ?IsTenant
    {
        return $this->findTenant($request);
    }
}
