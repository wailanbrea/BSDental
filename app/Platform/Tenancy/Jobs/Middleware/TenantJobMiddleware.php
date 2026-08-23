<?php

namespace App\Platform\Tenancy\Jobs\Middleware;

use App\Platform\Tenancy\Exceptions\TenantNotFoundException;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Closure;

class TenantJobMiddleware
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Process the queued job with isolated tenant context and ensure cleanup.
     */
    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = null;

        if (method_exists($job, 'getTenantId')) {
            $tenantId = $job->getTenantId();
        } elseif (property_exists($job, 'tenantId')) {
            $tenantId = $job->tenantId;
        }

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);

            if ($tenant === null) {
                throw TenantNotFoundException::forId($tenantId);
            }

            $this->tenantContext->makeCurrent($tenant);
        }

        try {
            return $next($job);
        } finally {
            $this->tenantContext->forgetCurrent();
        }
    }
}
