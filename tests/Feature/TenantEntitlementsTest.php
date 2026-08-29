<?php

use App\Core\Auth\Models\User;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Middleware\EnsureModuleEntitlement;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->starterPlan = Plan::create([
        'id' => 'starter',
        'name' => 'Plan Inicial Clínico',
        'modules' => ['patients', 'agenda', 'clinical', 'odontogram'],
        'max_users' => 3,
        'max_branches' => 1,
        'is_active' => true,
    ]);

    $this->enterprisePlan = Plan::create([
        'id' => 'enterprise',
        'name' => 'Plan Red Dental Enterprise',
        'modules' => ['patients', 'agenda', 'clinical', 'odontogram', 'quotes', 'inventory', 'lab', 'billing', 'finance', 'marketing', 'analytics'],
        'max_users' => 50,
        'max_branches' => 10,
        'is_active' => true,
    ]);

    $this->tenantStarter = Tenant::create([
        'name' => 'Clínica Pequeña',
        'slug' => 'clinica-pequena',
        'database_name' => $this->tenantDatabasePath('tenant_starter_test.sqlite'),
        'plan_id' => 'starter',
        'status' => 'active',
    ]);

    $this->tenantEnterprise = Tenant::create([
        'name' => 'Centro Médico Dental',
        'slug' => 'centro-dental',
        'database_name' => $this->tenantDatabasePath('tenant_enterprise_test.sqlite'),
        'plan_id' => 'enterprise',
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenantStarter->id,
        'domain' => 'clinica-pequena.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);
    $context->execute($this->tenantStarter, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $this->user = User::create([
            'name' => 'Clinic Owner',
            'email' => 'owner@clinica-pequena.test',
            'password' => Hash::make('SecretPassword123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);
    });
});

test('tenant inherits module entitlements from commercial plan', function () {
    expect($this->tenantStarter->hasModuleEntitlement('patients'))->toBeTrue()
        ->and($this->tenantStarter->hasModuleEntitlement('agenda'))->toBeTrue()
        ->and($this->tenantStarter->hasModuleEntitlement('lab'))->toBeFalse()
        ->and($this->tenantStarter->hasModuleEntitlement('marketing'))->toBeFalse();

    expect($this->tenantEnterprise->hasModuleEntitlement('lab'))->toBeTrue()
        ->and($this->tenantEnterprise->hasModuleEntitlement('finance'))->toBeTrue();
});

test('tenant custom override takes precedence over commercial plan', function () {
    // Override 'lab' to true on starter tenant
    $this->tenantStarter->update([
        'settings' => [
            'module_overrides' => [
                'lab' => true,
                'patients' => false,
            ],
        ],
    ]);

    expect($this->tenantStarter->hasModuleEntitlement('lab'))->toBeTrue()
        ->and($this->tenantStarter->hasModuleEntitlement('patients'))->toBeFalse();
});

test('ensure module entitlement middleware enforces access and blocks unentitled requests with 403', function () {
    $context = app(TenantContext::class);
    $middleware = app(EnsureModuleEntitlement::class);

    $context->makeCurrent($this->tenantStarter);

    // Requesting entitled module succeeds
    $requestPatients = Request::create('http://clinica-pequena.bsdental.test/patients');
    $response = $middleware->handle($requestPatients, fn () => response('OK'), 'patients');
    expect($response->getContent())->toBe('OK');

    // Requesting unentitled module throws 403
    $requestLab = Request::create('http://clinica-pequena.bsdental.test/lab');
    expect(fn () => $middleware->handle($requestLab, fn () => response('OK'), 'lab'))
        ->toThrow(HttpException::class);
});

test('tenant module routes deny unentitled access and allow an entitled override', function () {
    $this->actingAs($this->user, 'web')
        ->get('http://clinica-pequena.bsdental.test/inventory')
        ->assertForbidden();

    $this->tenantStarter->update([
        'settings' => [
            'module_overrides' => ['inventory' => true],
        ],
    ]);

    $this->actingAs($this->user, 'web')
        ->get('http://clinica-pequena.bsdental.test/inventory')
        ->assertOk();
});
