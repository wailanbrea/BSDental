<?php

namespace App\Platform\Tenancy\Middleware;

use App\Platform\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureModuleEntitlement
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request checking commercial module entitlement.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = $this->tenantContext->current();

        if ($tenant === null) {
            throw new HttpException(400, 'Contexto de clínica requerido para validar módulos.');
        }

        if (! $tenant->hasModuleEntitlement($module)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "El módulo '{$module}' no está habilitado en el plan de su clínica.",
                    'module' => $module,
                ], 403);
            }

            throw new HttpException(403, "El módulo '{$module}' no está incluido en el plan activo de su clínica.");
        }

        return $next($request);
    }
}
