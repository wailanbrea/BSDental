<?php

namespace App\Platform\Tenancy\Middleware;

use App\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContextCleaned
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request and ensure tenant context is wiped in finally block.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            $this->tenantContext->forgetCurrent();
        }
    }
}
