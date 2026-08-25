<?php

use App\Core\Auth\Models\Role;
use App\Core\Auth\Models\User;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $seeder = new DatabaseSeeder($this->tenantDatabasePath('tenant_demo.sqlite'));
    $command = new Command;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));
    $seeder->setCommand($command);
    $seeder->run();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});
it('refuses to seed the persistent demo database while testing', function () {
    expect(fn () => (new DatabaseSeeder)->run())
        ->toThrow(RuntimeException::class, 'explicit isolated tenant database path');
});

it('assigns the configured primary domain to the demo tenant', function () {
    config()->set('multitenancy.demo_tenant_domain', 'bsdental.bsolutions.dev');

    $seeder = new DatabaseSeeder($this->tenantDatabasePath('tenant_custom_domain.sqlite'));
    $command = new Command;
    $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));
    $seeder->setCommand($command);
    $seeder->run();

    $primaryDomain = TenantDomain::query()->where('is_primary', true)->sole();

    expect($primaryDomain->domain)->toBe('bsdental.bsolutions.dev')
        ->and(TenantDomain::query()->where('domain', 'demo.localhost')->value('is_primary'))->toBeFalsy();
});

it('authenticates every demo user and assigns their appropriate clinical role', function (string $email, string $expectedRole) {
    $tenant = Tenant::where('slug', 'demo')->first();
    expect($tenant)->not->toBeNull();

    $context = app(TenantContext::class);
    $context->execute($tenant, function () use ($email, $expectedRole) {
        $user = User::where('email', $email)->first();
        expect($user)->not->toBeNull("User with email {$email} must exist in tenant database.");
        expect(Hash::check('Password123!', $user->password))->toBeTrue("Password check failed for {$email}");
        expect($user->hasRole($expectedRole))->toBeTrue("User {$email} must have role {$expectedRole}");
        expect($user->status)->toBe('active');
    });
})->with([
    ['owner@bsdental.com', 'Owner'],
    ['doctor@bsdental.com', 'GeneralDentist'],
    ['recepcion@bsdental.com', 'Receptionist'],
    ['cajero@bsdental.com', 'Cashier'],
    ['almacen@bsdental.com', 'InventoryManager'],
]);

it('serves tailored dashboard for inventory manager without clinical or financial data', function () {
    $tenant = Tenant::where('slug', 'demo')->first();
    $context = app(TenantContext::class);
    $context->makeCurrent($tenant);

    $user = User::where('email', 'almacen@bsdental.com')->first();
    $this->actingAs($user, 'web');

    $response = $this->get('http://demo.localhost/dashboard');
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Clinic/Dashboard')
        ->where('user.primary_role', 'InventoryManager')
        ->has('inventoryData.critical_items')
        ->has('inventoryData.recent_movements')
        ->where('kpis.appointments_today', 0)
        ->where('kpis.net_collected_today', 0)
        ->where('kpis.accounts_receivable', 0)
        ->has('todayAppointments', 0)
        ->has('financialChart', 0)
        ->where('alerts.overdue_accounts_count', 0)
        ->where('alerts.pending_lab_orders_count', 0)
    );

    $context->makeCurrent($tenant);
    $this->actingAs($user, 'web');
    $inventoryResponse = $this->get('http://demo.localhost/inventory');
    $inventoryResponse->assertOk();
    $inventoryResponse->assertInertia(fn (Assert $page) => $page
        ->component('Clinic/Inventory/Index')
        ->has('items')
        ->has('categories')
    );
});

it('allows the owner to assign any role to any user and create custom users with any role', function () {
    $tenant = Tenant::where('slug', 'demo')->first();
    $context = app(TenantContext::class);

    $context->execute($tenant, function () {
        // 1. Check all available roles
        $roles = Role::pluck('name')->toArray();
        expect($roles)->toContain('Owner', 'ClinicDirector', 'GeneralDentist', 'SpecialistDentist', 'Hygienist', 'Receptionist', 'Cashier', 'LabTechnician', 'InventoryManager');

        // 2. Change role of a user
        $user = User::where('email', 'almacen@bsdental.com')->first();
        try {
            $user->syncRoles(['LabTechnician']);
            expect($user->hasRole('LabTechnician'))->toBeTrue();
            expect($user->hasRole('InventoryManager'))->toBeFalse();
        } finally {
            $user->syncRoles(['InventoryManager']);
        }

        // 3. Create a new user with any custom role (e.g. SpecialistDentist)
        $newUser = User::create([
            'name' => 'Dr. Ortodoncista Especialista',
            'email' => 'ortodoncia@bsdental.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        $newUser->assignRole('SpecialistDentist');
        expect($newUser->hasRole('SpecialistDentist'))->toBeTrue();
    });
});
