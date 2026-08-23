<?php

namespace App\Platform\Auth\Middleware;

use App\Platform\Auth\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformTwoFactor
{
    /**
     * Handle an incoming request ensuring 2FA verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('platform');

        /** @var PlatformUser|null $user */
        $user = $guard->user();

        if ($user !== null && $user->hasTwoFactorEnabled()) {
            $verified = $request->session()->get('platform.2fa_verified', false);

            if (! $verified && ! $request->routeIs('platform.two-factor*') && ! $request->routeIs('platform.logout')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Two factor authentication required.'], 403);
                }

                return redirect()->route('platform.two-factor');
            }
        }

        return $next($request);
    }
}
