<?php

namespace App\Platform\Security\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    /**
     * Handle an incoming request and attach OWASP ASVS baseline security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure() || app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $csp = "default-src 'self'; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self' ws: wss:; frame-ancestors 'self';";
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
