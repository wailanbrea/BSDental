<?php

namespace App\Platform\Tenancy\Middleware;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantFinder\DomainTenantFinder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveTenantFromHost
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected DomainTenantFinder $tenantFinder
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(trim($request->getHost()));
        /** @var list<string> $centralDomains */
        $centralDomains = config('multitenancy.central_domains', [
            'localhost',
            '127.0.0.1',
            'bsdental.test',
            'admin.bsdental.app',
        ]);

        // If host is explicitly marked as central/admin domain, don't enforce tenant resolution
        if (in_array($host, $centralDomains, true)) {
            return $next($request);
        }

        $tenant = $this->tenantFinder->findTenant($request);

        if (! $tenant instanceof Tenant) {
            throw new NotFoundHttpException('Clínica u organización no encontrada o dominio no verificado.');
        }

        if ($tenant->isSuspended()) {
            throw new HttpException(403, 'El acceso a esta clínica ha sido suspendido temporalmente por administración.');
        }

        if ($tenant->isProvisioning()) {
            throw new HttpException(503, 'La clínica está en proceso de configuración y aprovisionamiento.');
        }

        $this->tenantContext->makeCurrent($tenant);

        return $next($request);
    }
}
