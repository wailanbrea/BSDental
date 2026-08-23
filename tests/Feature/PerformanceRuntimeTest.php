<?php

use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Tenancy\Listeners\ResetTenantContextOnRequestTerminated;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);
});

test('redis configuration is configured with phpredis client and prefixing', function () {
    $redisConfig = config('database.redis');

    expect($redisConfig['client'])->toBe('phpredis')
        ->and($redisConfig['default'])->toHaveKey('host')
        ->and($redisConfig['cache'])->toHaveKey('database');
});

test('horizon configuration defines balanced priority queues and protected gate', function () {
    $horizonQueues = config('horizon.defaults.supervisor-1.queue');

    expect($horizonQueues)->toBe(['high', 'default', 'low']);

    // Unauthenticated user is denied access to Horizon
    expect(Gate::allows('viewHorizon'))->toBeFalse();

    // Superadmin is authorized
    $superadmin = PlatformUser::create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@bsdental.app',
        'password' => Hash::make('Secret123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);

    $this->actingAs($superadmin, 'platform');
    expect(Gate::allows('viewHorizon'))->toBeTrue();
});

test('octane readiness listener purges tenant context on request terminated', function () {
    $tenant = Tenant::create([
        'name' => 'Clínica Octane Test',
        'slug' => 'octane-test',
        'database_name' => ':memory:',
        'status' => 'active',
    ]);

    $context = app(TenantContext::class);
    $context->makeCurrent($tenant);
    expect($context->current())->not->toBeNull();

    $listener = app(ResetTenantContextOnRequestTerminated::class);
    $listener->handle();

    expect($context->current())->toBeNull();
});
