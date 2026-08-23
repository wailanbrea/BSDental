<?php

use App\Core\Auth\Middleware\EnsureTenantBranchAccess;
use App\Http\Middleware\HandleInertiaRequests;
use App\Platform\Security\Middleware\SetSecurityHeaders;
use App\Platform\Tenancy\Middleware\EnsureTenantContextCleaned;
use App\Platform\Tenancy\Middleware\ResolveTenantFromHost;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'branch.access' => EnsureTenantBranchAccess::class,
        ]);

        $middleware->web(
            append: [
                SetSecurityHeaders::class,
                HandleInertiaRequests::class,
            ],
            prepend: [
                // Resolve before session-backed auth, guest checks, Inertia and
                // route bindings can attempt to hydrate a tenant User model.
                ResolveTenantFromHost::class,
                EnsureTenantContextCleaned::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
