<?php

namespace App\Platform\Tenancy\Middleware;

use App\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventCentralDomainFromAccessingTenantDb
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Ensure central domain routes do not execute with an active tenant context.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->forgetCurrent();

        return $next($request);
    }
}
