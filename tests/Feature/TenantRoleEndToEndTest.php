<?php

use App\Core\Auth\Database\Seeders\TenantRbacSeeder;
use App\Core\Auth\Models\Role;
use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\Patient;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathE2E = $this->tenantDatabasePath('tenant_e2e_roles_test.sqlite');
    if (! file_exists($this->dbPathE2E)) {
        touch($this->dbPathE2E);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica E2E Roles Test',
        'slug' => 'e2e-roles',
        'database_name' => $this->dbPathE2E,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'e2e.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);
    $context->execute($this->tenant, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $seeder = new TenantRbacSeeder;
        $seeder->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->branch = Branch::create([
            'name' => 'Sede Principal E2E',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-99001',
            'first_name' => 'Mario',
            'last_name' => 'Vargas',
            'phone' => '+58 414 111-2233',
            'status' => 'active',
        ]);

        // Create Users for each key role
        $this->userOwner = User::create([
            'name' => 'Director Propietario',
            'email' => 'owner@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userOwner->assignRole('Owner');

        $this->userDentist = User::create([
            'name' => 'Dr. Especialista',
            'email' => 'dentist@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userDentist->assignRole('GeneralDentist');

        $this->userReceptionist = User::create([
            'name' => 'Recepcionista Turno',
            'email' => 'reception@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userReceptionist->assignRole('Receptionist');

        $this->userCashier = User::create([
            'name' => 'Cajero Principal',
            'email' => 'cashier@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userCashier->assignRole('Cashier');

        $this->userInventory = User::create([
            'name' => 'Encargado de Farmacia',
            'email' => 'inventory@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $this->userInventory->assignRole('InventoryManager');
    });
});

test('[REL-01] Owner has full unrestricted operational and clinical access', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->userOwner, 'web');

    $this->get('http://e2e.bsdental.test/dashboard')->assertOk();
    $this->get('http://e2e.bsdental.test/patients')->assertOk();
    $this->get('http://e2e.bsdental.test/appointments')->assertOk();
    $this->get('http://e2e.bsdental.test/encounters')->assertOk();
    $this->get('http://e2e.bsdental.test/quotes')->assertOk();
    $this->get('http://e2e.bsdental.test/inventory')->assertOk();
    $this->get('http://e2e.bsdental.test/lab')->assertOk();
    $this->get('http://e2e.bsdental.test/cash-registers')->assertOk();
    $this->get('http://e2e.bsdental.test/billing/aging-receivables')->assertOk();
    $this->get('http://e2e.bsdental.test/crm')->assertOk();
    $this->get('http://e2e.bsdental.test/analytics')->assertOk();
    $this->get('http://e2e.bsdental.test/users')->assertOk();
    $this->get('http://e2e.bsdental.test/settings')->assertOk();
});

test('[REL-01] Dentist has clinical, quote, and lab access but is blocked from settings and financial reports', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->userDentist, 'web');

    // Allowed
    $this->get('http://e2e.bsdental.test/dashboard')->assertOk();
    $this->get('http://e2e.bsdental.test/patients')->assertOk();
    $this->get('http://e2e.bsdental.test/appointments')->assertOk();
    $this->get('http://e2e.bsdental.test/encounters')->assertOk();
    $this->get('http://e2e.bsdental.test/quotes')->assertOk();

    // Forbidden
    $this->get('http://e2e.bsdental.test/users')->assertForbidden();
    $this->get('http://e2e.bsdental.test/settings')->assertForbidden();
    $this->get('http://e2e.bsdental.test/analytics')->assertForbidden();
    $this->get('http://e2e.bsdental.test/cash-registers')->assertForbidden();
    $this->get('http://e2e.bsdental.test/billing/aging-receivables')->assertForbidden();
});

test('[REL-01] Receptionist can manage agenda and patients but cannot write clinical history or view executive analytics', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->userReceptionist, 'web');

    // Allowed
    $this->get('http://e2e.bsdental.test/dashboard')->assertOk();
    $this->get('http://e2e.bsdental.test/patients')->assertOk();
    $this->get('http://e2e.bsdental.test/appointments')->assertOk();
    $this->get('http://e2e.bsdental.test/crm')->assertOk();
    $this->get('http://e2e.bsdental.test/cash-registers')->assertOk();

    // Forbidden
    $this->get('http://e2e.bsdental.test/users')->assertForbidden();
    $this->get('http://e2e.bsdental.test/settings')->assertForbidden();
    $this->get('http://e2e.bsdental.test/analytics')->assertForbidden();
    $this->get('http://e2e.bsdental.test/encounters')->assertForbidden();
    $this->get('http://e2e.bsdental.test/inventory')->assertForbidden();
});

test('[REL-01] Cashier has billing and cash register access but cannot mutate clinical records or settings', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->userCashier, 'web');

    // Allowed
    $this->get('http://e2e.bsdental.test/dashboard')->assertOk();
    $this->get('http://e2e.bsdental.test/cash-registers')->assertOk();
    $this->get("http://e2e.bsdental.test/patients/{$this->patient->id}/billing")->assertOk();
    $this->get("http://e2e.bsdental.test/patients/{$this->patient->id}/billing/statement")->assertOk();

    // Forbidden
    $this->get('http://e2e.bsdental.test/users')->assertForbidden();
    $this->get('http://e2e.bsdental.test/settings')->assertForbidden();
    $this->get('http://e2e.bsdental.test/encounters')->assertForbidden();
    $this->get('http://e2e.bsdental.test/inventory')->assertForbidden();
    $this->get('http://e2e.bsdental.test/lab')->assertForbidden();
});

test('[REL-01] Inventory Manager has stock management access but is blocked from cash and clinical records', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->userInventory, 'web');

    // Allowed
    $this->get('http://e2e.bsdental.test/dashboard')->assertOk();
    $this->get('http://e2e.bsdental.test/inventory')->assertOk();

    // Forbidden
    $this->get('http://e2e.bsdental.test/users')->assertForbidden();
    $this->get('http://e2e.bsdental.test/settings')->assertForbidden();
    $this->get('http://e2e.bsdental.test/cash-registers')->assertForbidden();
    $this->get('http://e2e.bsdental.test/encounters')->assertForbidden();
    $this->get('http://e2e.bsdental.test/quotes')->assertForbidden();
});
