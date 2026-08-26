<?php

use App\Core\Auth\Models\User;
use App\Core\Auth\Notifications\TenantResetPasswordNotification;
use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathA = $this->tenantDatabasePath('tenant_a_auth_test.sqlite');
    $this->dbPathB = $this->tenantDatabasePath('tenant_b_auth_test.sqlite');

    if (! file_exists($this->dbPathA)) {
        touch($this->dbPathA);
    }
    if (! file_exists($this->dbPathB)) {
        touch($this->dbPathB);
    }

    $this->tenantA = Tenant::create([
        'name' => 'Clínica Dental Alfa',
        'slug' => 'clinica-alfa',
        'database_name' => $this->dbPathA,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenantA->id,
        'domain' => 'alfa.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $this->tenantB = Tenant::create([
        'name' => 'Clínica Dental Beta',
        'slug' => 'clinica-beta',
        'database_name' => $this->dbPathB,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenantB->id,
        'domain' => 'beta.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);

    // Setup Tenant A database & clinic user
    $context->execute($this->tenantA, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        ClinicProfile::create([
            'clinic_name' => 'Alfa Dental Clinic',
            'legal_name' => 'Clínica Dental Alfa S.R.L.',
            'trade_name' => 'Alfa Dental Clinic',
            'tax_id' => 'ALFA-AUTH-01',
            'email' => 'contacto@alfadental.com',
            'currency' => 'USD',
            'timezone' => 'America/Santo_Domingo',
        ]);

        User::create([
            'name' => 'Dr. Carlos Mendoza',
            'email' => 'carlos@alfadental.com',
            'password' => Hash::make('SecretClinicPass123!'),
            'status' => 'active',
        ]);
    });

    // Setup Tenant B database
    $context->execute($this->tenantB, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);
    });
});

test('tenant login page displays clinic details for verified host', function () {
    $this->get('http://alfa.bsdental.test/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('clinic.trade_name', 'Alfa Dental Clinic')
        );
});

test('expired csrf sessions return to a recoverable page instead of rendering a 419', function () {
    Route::middleware('web')->post('/expired-session-test', function () {
        throw new TokenMismatchException;
    });

    $this->withHeader('Referer', 'http://alfa.bsdental.test/login')
        ->post('http://alfa.bsdental.test/expired-session-test')
        ->assertRedirect('http://alfa.bsdental.test/login')
        ->assertSessionHas('error', 'Tu sesión expiró. La página fue recargada; intenta realizar la acción nuevamente.')
        ->assertSessionHas('status', 'Tu sesión expiró. Inicia sesión nuevamente para continuar.');
});

test('tenant login fails with invalid credentials', function () {
    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'wrong-pass',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('tenant login succeeds and redirects to clinic dashboard', function () {
    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertRedirect(route('clinic.dashboard'));

    expect(Auth::guard('web')->check())->toBeTrue();
});

test('authenticated tenant request restores the user only after resolving the tenant', function () {
    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertRedirect(route('clinic.dashboard'));

    // Mimic the next HTTP request, where the guard must rebuild the user from
    // the session instead of reusing the in-memory instance from login.
    Auth::forgetGuards();

    $this->get('http://alfa.bsdental.test/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Dashboard')
            ->where('user.email', 'carlos@alfadental.com')
        );
});

test('tenant login route checks an existing session only after resolving the tenant', function () {
    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertRedirect(route('clinic.dashboard'));

    // The guest middleware must rebuild the session user under Tenant A.
    Auth::forgetGuards();

    $this->get('http://alfa.bsdental.test/login')
        ->assertRedirect('/dashboard');
});

test('tenant credentials on Tenant A do not work on Tenant B', function () {
    // Attempt login with Tenant A user credentials on Tenant B domain
    $this->post('http://beta.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('inactive or locked tenant user is rejected with 403', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        User::where('email', 'carlos@alfadental.com')->update(['status' => 'locked']);
    });

    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertForbidden();
});

test('tenant user with 2FA requires two-factor challenge', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        $user = User::where('email', 'carlos@alfadental.com')->firstOrFail();
        $user->two_factor_secret = '123456';
        $user->two_factor_confirmed_at = now();
        $user->save();
    });

    $this->post('http://alfa.bsdental.test/login', [
        'email' => 'carlos@alfadental.com',
        'password' => 'SecretClinicPass123!',
    ])->assertRedirect(route('two-factor'));

    // Accessing dashboard without 2FA redirects to challenge
    $this->get('http://alfa.bsdental.test/dashboard')
        ->assertRedirect(route('two-factor'));

    // Valid code grants access
    $this->post('http://alfa.bsdental.test/two-factor', ['code' => '123456'])
        ->assertRedirect(route('clinic.dashboard'));

    $this->get('http://alfa.bsdental.test/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Dashboard')
            ->where('clinic.trade_name', 'Alfa Dental Clinic')
            ->where('user.email', 'carlos@alfadental.com')
        );
});

test('tenant password recovery page preserves clinic identity and does not disclose unknown accounts', function () {
    Notification::fake();

    $this->get('http://alfa.bsdental.test/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/ForgotPassword')
            ->where('clinic.trade_name', 'Alfa Dental Clinic')
        );

    $response = $this->post('http://alfa.bsdental.test/forgot-password', [
        'email' => 'no-existe@alfadental.com',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('status', 'Si el correo corresponde a una cuenta activa, recibirás un enlace para restablecer la contraseña.');

    Notification::assertNothingSent();

    app(TenantContext::class)->makeCurrent($this->tenantA);
    expect(DB::connection('tenant')->table('password_reset_tokens')->count())->toBe(0);
});

test('tenant user can reset password with a hashed single-use token', function () {
    Notification::fake();
    $resetUrl = null;

    $this->post('http://alfa.bsdental.test/forgot-password', [
        'email' => 'CARLOS@ALFADENTAL.COM',
    ])->assertRedirect()
        ->assertSessionHas('status');

    app(TenantContext::class)->makeCurrent($this->tenantA);
    $user = User::where('email', 'carlos@alfadental.com')->firstOrFail();

    Notification::assertSentTo(
        $user,
        TenantResetPasswordNotification::class,
        function (TenantResetPasswordNotification $notification) use (&$resetUrl): bool {
            $resetUrl = $notification->resetUrl;

            return str_starts_with($notification->resetUrl, 'http://alfa.bsdental.test/reset-password/')
                && str_contains($notification->resetUrl, 'email=carlos%40alfadental.com');
        }
    );

    expect($resetUrl)->toBeString();
    $path = (string) parse_url($resetUrl, PHP_URL_PATH);
    $token = basename($path);
    $storedToken = DB::connection('tenant')->table('password_reset_tokens')
        ->where('email', 'carlos@alfadental.com')
        ->value('token');

    expect($storedToken)->not->toBe($token)
        ->and(Hash::check($token, $storedToken))->toBeTrue();

    $this->post('http://alfa.bsdental.test/reset-password', [
        'token' => $token,
        'email' => 'carlos@alfadental.com',
        'password' => 'NuevaClaveSegura#2026',
        'password_confirmation' => 'NuevaClaveSegura#2026',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Tu contraseña fue actualizada. Ya puedes iniciar sesión.');

    app(TenantContext::class)->makeCurrent($this->tenantA);
    $user->refresh();
    expect(Hash::check('NuevaClaveSegura#2026', $user->password))->toBeTrue()
        ->and(DB::connection('tenant')->table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(TenantAuditLog::where('action', 'auth.password_reset_completed')->exists())->toBeTrue();

    $this->post('http://alfa.bsdental.test/reset-password', [
        'token' => $token,
        'email' => 'carlos@alfadental.com',
        'password' => 'OtraClaveSegura#2026',
        'password_confirmation' => 'OtraClaveSegura#2026',
    ])->assertSessionHasErrors('email');
});

test('tenant reset tokens expire and cannot cross tenant boundaries', function () {
    Notification::fake();
    $resetUrl = null;

    $this->post('http://alfa.bsdental.test/forgot-password', [
        'email' => 'carlos@alfadental.com',
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenantA);
    $user = User::where('email', 'carlos@alfadental.com')->firstOrFail();
    Notification::assertSentTo($user, TenantResetPasswordNotification::class, function ($notification) use (&$resetUrl): bool {
        $resetUrl = $notification->resetUrl;

        return true;
    });
    $token = basename((string) parse_url($resetUrl, PHP_URL_PATH));

    $this->post('http://beta.bsdental.test/reset-password', [
        'token' => $token,
        'email' => 'carlos@alfadental.com',
        'password' => 'FronteraSegura#2026',
        'password_confirmation' => 'FronteraSegura#2026',
    ])->assertSessionHasErrors('email');

    app(TenantContext::class)->makeCurrent($this->tenantA);
    DB::connection('tenant')->table('password_reset_tokens')
        ->where('email', 'carlos@alfadental.com')
        ->update(['created_at' => now()->subMinutes(61)]);

    $this->post('http://alfa.bsdental.test/reset-password', [
        'token' => $token,
        'email' => 'carlos@alfadental.com',
        'password' => 'CaducadaSegura#2026',
        'password_confirmation' => 'CaducadaSegura#2026',
    ])->assertSessionHasErrors('email');
});
