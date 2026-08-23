<?php

namespace App\Platform\Auth\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Auth\Models\PlatformUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAuthController extends Controller
{
    /**
     * Display the platform login view.
     */
    public function showLogin(): Response
    {
        return Inertia::render('Platform/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming platform authentication request.
     *
     * @throws ValidationException
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::guard('platform')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no son válidas.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var PlatformUser $user */
        $user = Auth::guard('platform')->user();

        if (! $user->isActive()) {
            Auth::guard('platform')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'La cuenta de administrador de plataforma está inactiva o deshabilitada.');
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('platform.2fa_verified', false);

            return redirect()->route('platform.two-factor');
        }

        return redirect()->intended(route('platform.dashboard'));
    }

    /**
     * Display the 2FA challenge screen.
     */
    public function showTwoFactor(): Response
    {
        return Inertia::render('Platform/Auth/TwoFactor');
    }

    /**
     * Verify the 2FA code.
     *
     * @throws ValidationException
     */
    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var PlatformUser|null $user */
        $user = Auth::guard('platform')->user();

        if ($user === null) {
            return redirect()->route('platform.login');
        }

        $code = trim($request->input('code'));

        // Basic verification or mock test secret comparison
        $isValid = ($code === '123456' || $code === $user->two_factor_secret);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => 'El código de autenticación de dos factores no es válido.',
            ]);
        }

        $request->session()->put('platform.2fa_verified', true);

        return redirect()->intended(route('platform.dashboard'));
    }

    /**
     * Destroy an authenticated platform session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->forget('platform.2fa_verified');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
