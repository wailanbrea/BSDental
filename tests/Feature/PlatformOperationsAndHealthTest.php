<?php

use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Plans\Models\Plan;
use App\Platform\Security\Models\LandlordAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathOps = database_path('tenant_gate_ops_test.sqlite');
    if (! file_exists($this->dbPathOps)) {
        touch($this->dbPathOps);
    }

    $this->planA = Plan::create([
        'name' => 'Plan Clínico Profesional',
        'modules' => ['billing', 'clinical', 'inventory'],
        'is_active' => true,
    ]);

    $this->planB = Plan::create([
        'name' => 'Plan Enterprise Multi-Sede',
        'modules' => ['billing', 'clinical', 'inventory', 'lab', 'crm'],
        'is_active' => true,
    ]);

    $this->tenant = Tenant::create([
        'name' => 'Clínica Operaciones Landlord',
        'slug' => 'ops-test',
        'database_name' => $this->dbPathOps,
        'status' => 'active',
        'plan_id' => $this->planA->id,
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'ops.bsdental.test',
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
    });

    $this->platformUser = PlatformUser::create([
        'name' => 'Super Admin Platform Ops',
        'email' => 'ops-admin@bsdental.io',
        'password' => Hash::make('PlatformAdminSecure2026!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);
});

test('[GATE-ADM] Platform operations dashboard, database health inspection, backup drill and canary migration', function () {
    // 1. Authenticate as Platform Admin with 2FA
    $this->actingAs($this->platformUser, 'platform');
    session(['platform_auth_2fa_passed' => true]);

    // 2. Access Platform Operations Dashboard
    $dashboardResponse = $this->get(route('platform.operations.dashboard'));
    $dashboardResponse->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Operations/Dashboard')
            ->has('metrics')
            ->has('tenants')
            ->has('plans')
        );

    // 3. Trigger Tenant Database Backup
    Storage::fake('local');
    $backupResponse = $this->post(route('platform.tenants.backup', $this->tenant->id));
    $backupResponse->assertRedirect();

    // Verify backup created in private tenant path
    $files = Storage::disk('local')->files("tenants/{$this->tenant->id}/backups");
    expect(count($files))->toBeGreaterThan(0);

    // Verify Landlord Audit Log
    expect(LandlordAuditLog::where('action', 'tenant.backup_triggered')->exists())->toBeTrue();

    // 4. Update Subscription Plan to Enterprise
    $planUpdateResponse = $this->post(route('platform.tenants.update_plan', $this->tenant->id), [
        'plan_id' => $this->planB->id,
    ]);
    $planUpdateResponse->assertRedirect();

    $this->tenant->refresh();
    expect($this->tenant->plan_id)->toBe($this->planB->id);
    expect(LandlordAuditLog::where('action', 'tenant.plan_updated')->exists())->toBeTrue();

    // 5. Run Artisan Canary Migration Command
    $exitCode = Artisan::call('tenants:migrate-canary');
    expect($exitCode)->toBe(0);
});
