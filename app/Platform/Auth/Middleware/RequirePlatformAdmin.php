<?php

namespace App\Platform\Auth\Middleware;

use App\Platform\Auth\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RequirePlatformAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('platform');

        if (! $guard->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->guest(route('platform.login'));
        }

        /** @var PlatformUser|null $user */
        $user = $guard->user();

        if ($user === null || ! $user->isActive()) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new HttpException(403, 'La cuenta de administrador de plataforma está inactiva o deshabilitada.');
        }

        return $next($request);
    }
}
