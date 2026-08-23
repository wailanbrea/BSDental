<?php

use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
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
        'name' => 'Admin Test',
        'email' => 'admin@bsdental.app',
        'password' => Hash::make('Secret123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);

    $this->plan = Plan::create([
        'id' => 'pro',
        'name' => 'Plan Pro Dental',
        'modules' => ['patients', 'agenda', 'clinical', 'odontogram', 'quotes', 'billing'],
        'max_users' => 10,
        'max_branches' => 2,
        'is_active' => true,
    ]);

    $this->dbPath = database_path('tenant_admin_test.sqlite');
    if (! file_exists($this->dbPath)) {
        touch($this->dbPath);
    }

    $this->tenant = Tenant::create([
        'name' => 'Centro Odontológico Bella Vista',
        'slug' => 'bella-vista',
        'database_name' => $this->dbPath,
        'plan_id' => 'pro',
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'bellavista.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    // Setup tenant DB with migrations
    $context = app(TenantContext::class);
    $context->execute($this->tenant, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);
    });
});

test('platform admin can view tenants listing with filters', function () {
    $this->actingAs($this->admin, 'platform')
        ->withSession(['platform.2fa_verified' => true])
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.slug', 'bella-vista')
        );
});

test('platform admin can inspect tenant detail and health status', function () {
    $this->actingAs($this->admin, 'platform')
        ->withSession(['platform.2fa_verified' => true])
        ->get(route('platform.tenants.show', $this->tenant->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Show')
            ->where('tenant.name', 'Centro Odontológico Bella Vista')
            ->where('health.database_status', 'healthy')
            ->where('health.applied_migrations', fn ($count) => $count > 0)
        );
});
