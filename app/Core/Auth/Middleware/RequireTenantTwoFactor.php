<?php

namespace App\Core\Auth\Middleware;

use App\Core\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantTwoFactor
{
    /**
     * Handle an incoming request ensuring clinic user 2FA verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');

        /** @var User|null $user */
        $user = $guard->user();

        if ($user !== null && $user->hasTwoFactorEnabled()) {
            $verified = $request->session()->get('tenant.2fa_verified', false);

            if (! $verified && ! $request->routeIs('two-factor*') && ! $request->routeIs('logout')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Two factor authentication required.'], 403);
                }

                return redirect()->route('two-factor');
            }
        }

        return $next($request);
    }
}
