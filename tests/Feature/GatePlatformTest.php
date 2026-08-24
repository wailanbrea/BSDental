<?php

use App\Core\Auth\Models\User;
use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->platformAdmin = PlatformUser::create([
        'name' => 'Platform Superadmin',
        'email' => 'superadmin@bsdental.app',
        'password' => Hash::make('AdminSecret123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);

    $this->proPlan = Plan::create([
        'id' => 'pro',
        'name' => 'Plan Profesional Dental',
        'modules' => ['patients', 'agenda', 'clinical', 'odontogram', 'quotes', 'billing'],
        'max_users' => 15,
        'max_branches' => 3,
        'is_active' => true,
    ]);

    $this->tenantCDbPath = $this->tenantDatabasePath('tenant_clinica-c.sqlite');
    if (file_exists($this->tenantCDbPath)) {
        unlink($this->tenantCDbPath);
    }
});

test('[GATE PL] Platform Admin creates Tenant C via UI/Pipeline and Tenant Owner authenticates with zero manual DB steps', function () {
    // 1. Platform Admin logs into Platform plane
    $this->post('/platform/login', [
        'email' => 'superadmin@bsdental.app',
        'password' => 'AdminSecret123!',
    ])->assertRedirect(route('platform.dashboard'));

    // 2. Platform Admin provisions Tenant C via POST /platform/tenants
    $response = $this->actingAs($this->platformAdmin, 'platform')
        ->withSession(['platform.2fa_verified' => true])
        ->post(route('platform.tenants.store'), [
            'name' => 'Clínica Dental C San Isidro',
            'slug' => 'clinica-c',
            'domain' => 'c.bsdental.test',
            'plan_id' => 'pro',
            'owner_name' => 'Dr. Carlos Céspedes',
            'owner_email' => 'carlos@clinicac.com',
            'owner_password' => 'CarlosPassword123!',
            'currency' => 'USD',
            'timezone' => 'America/Lima',
        ]);

    /** @var Tenant $tenantC */
    $tenantC = Tenant::where('slug', 'clinica-c')->firstOrFail();
    $response->assertRedirect(route('platform.tenants.show', $tenantC->id));

    // Verify Tenant C state in Landlord
    expect($tenantC->status)->toBe('active')
        ->and($tenantC->database_name)->toBe($this->tenantCDbPath)
        ->and($tenantC->hasModuleEntitlement('patients'))->toBeTrue()
        ->and($tenantC->hasModuleEntitlement('quotes'))->toBeTrue()
        ->and($tenantC->hasModuleEntitlement('marketing'))->toBeFalse(); // Not in 'pro' plan

    // 3. Authenticate Tenant C Owner directly on their subdomain with ZERO manual DB steps
    $tenantOwnerResponse = $this->withServerVariables(['HTTP_HOST' => 'c.bsdental.test'])
        ->post('http://c.bsdental.test/login', [
            'email' => 'carlos@clinicac.com',
            'password' => 'CarlosPassword123!',
        ]);

    $tenantOwnerResponse->assertRedirect(route('clinic.dashboard'));

    // 4. Verify Owner user roles & permissions in Tenant C Database
    $context = app(TenantContext::class);
    $context->execute($tenantC, function () {
        /** @var User $ownerUser */
        $ownerUser = User::where('email', 'carlos@clinicac.com')->firstOrFail();

        expect($ownerUser->hasRole('Owner'))->toBeTrue()
            ->and($ownerUser->can('patients.create'))->toBeTrue()
            ->and($ownerUser->can('clinical.finalize'))->toBeTrue()
            ->and($ownerUser->can('users.manage'))->toBeTrue();
    });
});
