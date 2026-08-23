<?php

namespace App\Core\Auth\Middleware;

use App\Core\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequireActiveTenantUser
{
    /**
     * Handle an incoming request for clinic user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        if (! $guard->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        /** @var User|null $user */
        $user = $guard->user();

        if ($user === null || ! $user->isActive()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new HttpException(403, 'La cuenta de usuario de la clínica está inactiva o deshabilitada.');
        }

        if ($user->isLocked()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new HttpException(403, 'La cuenta de usuario está bloqueada por seguridad.');
        }

        return $next($request);
    }
}
