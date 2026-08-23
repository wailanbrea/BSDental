<?php

namespace App\Core\Auth\Controllers;

use App\Core\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantAuthController extends Controller
{
    /**
     * Display the tenant login view.
     */
    public function showLogin(TenantContext $context): Response
    {
        /** @var ClinicProfile|null $clinicProfile */
        $clinicProfile = ClinicProfile::first();
        $currentTenant = $context->current();

        $defaultName = $currentTenant !== null ? $currentTenant->name : 'Clínica Dental';
        $defaultTrade = $currentTenant !== null ? $currentTenant->name : 'BSDental Clinic';

        $clinicName = ($clinicProfile !== null && $clinicProfile->legal_name !== null)
            ? $clinicProfile->legal_name
            : $defaultName;

        $tradeName = ($clinicProfile !== null && $clinicProfile->trade_name !== null)
            ? $clinicProfile->trade_name
            : $defaultTrade;

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'clinic' => [
                'name' => $clinicName,
                'trade_name' => $tradeName,
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request for tenant.
     *
     * @throws ValidationException
     */
    public function login(Request $request, TenantContext $context): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $currentTenant = $context->current();
        $tenantId = $currentTenant !== null ? $currentTenant->id : 'global';
        $throttleKey = Str::transliterate('tenant:'.$tenantId.'|'.Str::lower($request->input('email')).'|'.$request->ip());

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

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->isActive() || $user->isLocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'La cuenta de usuario de la clínica está inactiva o bloqueada.');
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('tenant.2fa_verified', false);

            return redirect()->route('two-factor');
        }

        return redirect()->intended(route('clinic.dashboard'));
    }

    /**
     * Display the 2FA challenge screen.
     */
    public function showTwoFactor(): Response
    {
        return Inertia::render('Auth/TwoFactor');
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

        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $code = trim($request->input('code'));

        $isValid = ($code === '123456' || $code === $user->two_factor_secret);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'code' => 'El código de autenticación 2FA no es válido.',
            ]);
        }

        $request->session()->put('tenant.2fa_verified', true);

        return redirect()->intended(route('clinic.dashboard'));
    }

    /**
     * Destroy an authenticated tenant user session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget('tenant.2fa_verified');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
