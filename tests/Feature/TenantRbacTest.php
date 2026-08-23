<?php

use App\Core\Auth\Database\Seeders\TenantRbacSeeder;
use App\Core\Auth\Models\Permission;
use App\Core\Auth\Models\Role;
use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathA = database_path('tenant_a_rbac_test.sqlite');
    $this->dbPathB = database_path('tenant_b_rbac_test.sqlite');

    if (! file_exists($this->dbPathA)) {
        touch($this->dbPathA);
    }
    if (! file_exists($this->dbPathB)) {
        touch($this->dbPathB);
    }

    $this->tenantA = Tenant::create([
        'name' => 'Clínica Dental A',
        'slug' => 'clinica-a',
        'database_name' => $this->dbPathA,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenantA->id,
        'domain' => 'a.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $this->tenantB = Tenant::create([
        'name' => 'Clínica Dental B',
        'slug' => 'clinica-b',
        'database_name' => $this->dbPathB,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenantB->id,
        'domain' => 'b.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);

    // Setup Tenant A DB & seed RBAC
    $context->execute($this->tenantA, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $seeder = new TenantRbacSeeder;
        $seeder->run();
    });

    // Setup Tenant B DB & seed RBAC
    $context->execute($this->tenantB, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $seeder = new TenantRbacSeeder;
        $seeder->run();
    });
});

test('tenant rbac seeds create predefined clinical roles and permissions', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        expect(Role::count())->toBe(9)
            ->and(Permission::count())->toBeGreaterThan(15)
            ->and(Role::where('name', 'Owner')->exists())->toBeTrue()
            ->and(Role::where('name', 'GeneralDentist')->exists())->toBeTrue()
            ->and(Role::where('name', 'Receptionist')->exists())->toBeTrue();
    });
});

test('owner role has full permissions access', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $owner = User::create([
            'name' => 'Dr. Propietario',
            'email' => 'owner@clinica-a.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $owner->assignRole('Owner');

        expect($owner->hasRole('Owner'))->toBeTrue()
            ->and($owner->can('patients.create'))->toBeTrue()
            ->and($owner->can('clinical.finalize'))->toBeTrue()
            ->and($owner->can('cash.reopen'))->toBeTrue()
            ->and($owner->can('users.manage'))->toBeTrue();
    });
});

test('dentist role has clinical permissions but no financial or user management permissions', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $dentist = User::create([
            'name' => 'Dra. Laura Gómez',
            'email' => 'laura@clinica-a.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $dentist->assignRole('GeneralDentist');

        expect($dentist->hasRole('GeneralDentist'))->toBeTrue()
            ->and($dentist->can('clinical.view'))->toBeTrue()
            ->and($dentist->can('clinical.write'))->toBeTrue()
            ->and($dentist->can('odontogram.write'))->toBeTrue()
            ->and($dentist->can('cash.reopen'))->toBeFalse()
            ->and($dentist->can('users.manage'))->toBeFalse();
    });
});

test('receptionist role has admission permissions but cannot write clinical data', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $receptionist = User::create([
            'name' => 'María Recepción',
            'email' => 'maria@clinica-a.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);

        $receptionist->assignRole('Receptionist');

        expect($receptionist->hasRole('Receptionist'))->toBeTrue()
            ->and($receptionist->can('appointments.create'))->toBeTrue()
            ->and($receptionist->can('patients.create'))->toBeTrue()
            ->and($receptionist->can('clinical.write'))->toBeFalse()
            ->and($receptionist->can('clinical.finalize'))->toBeFalse();
    });
});

test('rbac data is physically isolated between tenants', function () {
    $context = app(TenantContext::class);

    // Create user with Receptionist in Tenant A
    $context->execute($this->tenantA, function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $userA = User::create([
            'name' => 'User Tenant A',
            'email' => 'usera@clinica-a.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
        ]);
        $userA->assignRole('Receptionist');
    });

    // In Tenant B, user from Tenant A does not exist
    $context->execute($this->tenantB, function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        expect(User::where('email', 'usera@clinica-a.com')->exists())->toBeFalse();
    });
});

test('route permissions and user administration are enforced end to end', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenantA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $branch = Branch::create(['name' => 'Sede Norte', 'is_main' => true, 'is_active' => true]);
    $otherBranch = Branch::create(['name' => 'Sede Sur', 'is_main' => false, 'is_active' => true]);
    $owner = User::create([
        'name' => 'Owner Operativo', 'email' => 'owner.routes@test.com',
        'password' => Hash::make('Password123!'), 'status' => 'active',
    ]);
    $owner->assignRole('Owner');

    $dentist = User::create([
        'name' => 'Odontóloga Restringida', 'email' => 'dentist.routes@test.com',
        'password' => Hash::make('Password123!'), 'status' => 'active',
    ]);
    $dentist->assignRole('GeneralDentist');
    $dentist->branches()->sync([$branch->id]);

    $this->actingAs($dentist, 'web')
        ->get('http://a.bsdental.test/patients')
        ->assertOk();
    $this->get('http://a.bsdental.test/appointments')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('branches', 1)
            ->where('branches.0.id', $branch->id));
    $this->get('http://a.bsdental.test/cash-registers')->assertForbidden();
    $this->get('http://a.bsdental.test/users')->assertForbidden();
    $this->post('http://a.bsdental.test/appointments', ['branch_id' => $otherBranch->id])->assertForbidden();

    $this->actingAs($owner, 'web')
        ->get('http://a.bsdental.test/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Users/Index')
            ->has('roles', 9)
            ->has('branches', 2));

    $this->post('http://a.bsdental.test/users', [
        'name' => 'Nueva Recepción',
        'email' => 'recepcion.nueva@test.com',
        'phone' => '809-555-0101',
        'status' => 'active',
        'password' => 'SecurePassword123!',
        'role' => 'Receptionist',
        'branch_ids' => [$branch->id],
    ])->assertRedirect();

    $context->makeCurrent($this->tenantA);
    $created = User::where('email', 'recepcion.nueva@test.com')->firstOrFail();
    expect($created->hasRole('Receptionist'))->toBeTrue()
        ->and($created->branches()->pluck('branches.id')->all())->toBe([$branch->id])
        ->and(TenantAuditLog::where('action', 'users.created')->where('resource_id', $created->id)->exists())->toBeTrue();

    $receptionRole = Role::where('name', 'Receptionist')->firstOrFail();
    $permissions = $receptionRole->permissions()->pluck('name')->push('clinical.view')->unique()->values()->all();
    $this->put("http://a.bsdental.test/roles/{$receptionRole->id}/permissions", [
        'permissions' => $permissions,
    ])->assertRedirect();

    $context->makeCurrent($this->tenantA);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($created->fresh()->can('clinical.view'))->toBeTrue()
        ->and(TenantAuditLog::where('action', 'roles.permissions_updated')->where('resource_id', $receptionRole->id)->exists())->toBeTrue();
});
