<?php

use App\Platform\Actions\ResumeTenantAction;
use App\Platform\Actions\SuspendTenantAction;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->plan = Plan::create([
        'id' => 'starter',
        'name' => 'Plan Starter',
        'modules' => ['patients', 'agenda'],
        'max_users' => 5,
        'max_branches' => 1,
        'is_active' => true,
    ]);

    $this->dbPath = database_path('tenant_lifecycle_test.sqlite');
    if (! file_exists($this->dbPath)) {
        touch($this->dbPath);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Vida Dental',
        'slug' => 'vida-dental',
        'database_name' => $this->dbPath,
        'plan_id' => 'starter',
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'vidadental.bsdental.test',
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
        ClinicProfile::create([
            'clinic_name' => 'Clínica Vida Dental',
            'email' => 'info@vidadental.test',
        ]);
    });
});

test('suspend action changes status to suspended and blocks tenant requests with 403', function () {
    $suspendAction = app(SuspendTenantAction::class);
    $suspendAction->execute($this->tenant, 'Falta de pago de suscripción');

    expect($this->tenant->fresh()->status)->toBe('suspended')
        ->and($this->tenant->fresh()->settings['suspension_reason'])->toBe('Falta de pago de suscripción');

    // Request from host returns 403
    $this->get('http://vidadental.bsdental.test/login')
        ->assertStatus(403);
});

test('resume action restores tenant status to active and allows host access', function () {
    $this->tenant->update(['status' => 'suspended']);

    $resumeAction = app(ResumeTenantAction::class);
    $resumeAction->execute($this->tenant);

    expect($this->tenant->fresh()->status)->toBe('active');

    // Request from host succeeds
    $this->get('http://vidadental.bsdental.test/login')
        ->assertOk();
});
