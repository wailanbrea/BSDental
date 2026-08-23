<?php

namespace App\Core\Auth\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Auth\Notifications\TenantResetPasswordNotification;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantPasswordResetController extends Controller
{
    private const GENERIC_STATUS = 'Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer la contraseña.';

    public function create(TenantContext $context): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'clinic' => $this->clinicDetails($context),
        ]);
    }

    public function store(Request $request, TenantContext $context, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if ($user !== null) {
            $token = Str::random(64);

            DB::connection('tenant')->table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $query = http_build_query(['email' => $email]);
            $resetUrl = $request->getSchemeAndHttpHost().'/reset-password/'.rawurlencode($token).'?'.$query;
            $clinic = $this->clinicDetails($context);

            $user->notify(new TenantResetPasswordNotification($resetUrl, $clinic['trade_name']));

            $auditLogger->logTenant('auth.password_reset_requested', User::class, (string) $user->id);
        }

        return back()->with('status', self::GENERIC_STATUS);
    }

    public function edit(Request $request, string $token, TenantContext $context): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
            'clinic' => $this->clinicDetails($context),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $email = Str::lower(trim($validated['email']));
        $tokenRecord = DB::connection('tenant')
            ->table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        $expiresAt = $tokenRecord?->created_at !== null
            ? Carbon::parse($tokenRecord->created_at)->addMinutes(60)
            : null;

        if (
            $tokenRecord === null
            || $expiresAt === null
            || $expiresAt->isPast()
            || ! Hash::check($validated['token'], $tokenRecord->token)
        ) {
            throw ValidationException::withMessages([
                'email' => 'El enlace de restablecimiento no es válido o ha caducado.',
            ]);
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'El enlace de restablecimiento no es válido o ha caducado.',
            ]);
        }

        DB::connection('tenant')->transaction(function () use ($email, $user, $validated): void {
            $user->forceFill([
                'password' => $validated['password'],
                'remember_token' => Str::random(60),
            ])->save();

            DB::connection('tenant')->table('password_reset_tokens')->where('email', $email)->delete();
        });

        event(new PasswordReset($user));
        $auditLogger->logTenant('auth.password_reset_completed', User::class, (string) $user->id);

        return redirect()->route('login')->with('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');
    }

    /**
     * @return array{name: string, trade_name: string}
     */
    private function clinicDetails(TenantContext $context): array
    {
        $profile = ClinicProfile::query()->firstOrNew();
        $fallback = $context->requireCurrent()->name;

        return [
            'name' => $profile->legal_name ?? $fallback,
            'trade_name' => $profile->trade_name ?? $fallback,
        ];
    }
}
