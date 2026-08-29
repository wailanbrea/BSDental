<?php

use App\Platform\Auth\Models\PlatformUser;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->admin = PlatformUser::create([
        'name' => 'Super Admin BSolutions',
        'email' => 'admin@bsdental.app',
        'password' => Hash::make('SecretPassword123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);
});

test('platform login page is accessible', function () {
    $this->get(route('platform.login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Auth/Login'));
});

test('platform login fails with invalid credentials', function () {
    $this->post(route('platform.login'), [
        'email' => 'admin@bsdental.app',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    expect(Auth::guard('platform')->check())->toBeFalse();
});

test('platform login succeeds with valid credentials and redirects to dashboard', function () {
    $this->post(route('platform.login'), [
        'email' => 'admin@bsdental.app',
        'password' => 'SecretPassword123!',
    ])->assertRedirect(route('platform.dashboard'));

    expect(Auth::guard('platform')->check())->toBeTrue()
        ->and(Auth::guard('platform')->user()->id)->toBe($this->admin->id);
});

test('platform user with 2FA requires two-factor verification', function () {
    $this->admin->update([
        'two_factor_secret' => 'platform-test-secret',
        'two_factor_confirmed_at' => now(),
    ]);

    $this->post(route('platform.login'), [
        'email' => 'admin@bsdental.app',
        'password' => 'SecretPassword123!',
    ])->assertRedirect(route('platform.two-factor'));

    // Trying to access dashboard without confirming 2FA must redirect to two-factor challenge
    $this->get(route('platform.dashboard'))
        ->assertRedirect(route('platform.two-factor'));

    // Providing wrong code fails
    $this->post('/platform/two-factor', ['code' => '999999'])
        ->assertSessionHasErrors('code');

    // The former universal bypass must not satisfy this user's challenge.
    $this->post('/platform/two-factor', ['code' => '123456'])
        ->assertSessionHasErrors('code');

    // The configured per-user secret succeeds and allows dashboard access.
    $this->post('/platform/two-factor', ['code' => 'platform-test-secret'])
        ->assertRedirect(route('platform.dashboard'));

    $this->get(route('platform.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Dashboard'));
});

test('inactive platform user is blocked from accessing platform', function () {
    $this->admin->update(['is_active' => false]);

    $this->post(route('platform.login'), [
        'email' => 'admin@bsdental.app',
        'password' => 'SecretPassword123!',
    ])->assertForbidden();
});

test('non-admin platform users cannot access platform administration', function () {
    $supportUser = PlatformUser::create([
        'name' => 'Platform Support',
        'email' => 'support@bsdental.app',
        'password' => Hash::make('SecretPassword123!'),
        'role' => 'support',
        'is_active' => true,
    ]);

    $this->actingAs($supportUser, 'platform')
        ->withSession(['platform.2fa_verified' => true])
        ->get(route('platform.dashboard'))
        ->assertForbidden();
});

test('platform logout destroys session and redirects to login', function () {
    $this->actingAs($this->admin, 'platform')
        ->post(route('platform.logout'))
        ->assertRedirect(route('platform.login'));

    expect(Auth::guard('platform')->check())->toBeFalse();
});
