<?php

use App\Core\Auth\Models\Role;
use App\Core\Auth\Models\User;
use App\Platform\Plans\Models\Plan;
use App\Platform\Provisioning\TenantProvisioningPipeline;
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
        'modules' => ['patients', 'agenda', 'clinical'],
        'max_users' => 5,
        'max_branches' => 1,
        'is_active' => true,
    ]);

    $this->pipeline = app(TenantProvisioningPipeline::class);
    $this->testDbPath = database_path('tenant_pipeline_test.sqlite');
    if (file_exists($this->testDbPath)) {
        unlink($this->testDbPath);
    }
});

test('provisioning pipeline successfully creates tenant, db, migrations, rbac and owner user', function () {
    $tenant = $this->pipeline->run([
        'name' => 'Clínica Dental San Lucas',
        'slug' => 'san-lucas',
        'domain' => 'sanlucas.bsdental.test',
        'plan_id' => 'starter',
        'owner_name' => 'Dr. Lucas Morales',
        'owner_email' => 'lucas@sanlucasdental.com',
        'owner_password' => 'SecurePass123!',
        'database_name' => $this->testDbPath,
    ]);

    expect($tenant)->toBeInstanceOf(Tenant::class)
        ->and($tenant->status)->toBe('active')
        ->and($tenant->slug)->toBe('san-lucas');

    // Landlord records
    expect(TenantDomain::where('domain', 'sanlucas.bsdental.test')->exists())->toBeTrue();

    // Tenant DB checks
    $context = app(TenantContext::class);
    $context->execute($tenant, function () {
        expect(ClinicProfile::where('clinic_name', 'Clínica Dental San Lucas')->exists())->toBeTrue()
            ->and(Role::where('name', 'Owner')->exists())->toBeTrue()
            ->and(User::where('email', 'lucas@sanlucasdental.com')->exists())->toBeTrue();

        /** @var User $owner */
        $owner = User::where('email', 'lucas@sanlucasdental.com')->firstOrFail();
        expect($owner->hasRole('Owner'))->toBeTrue()
            ->and($owner->can('patients.create'))->toBeTrue()
            ->and($owner->can('clinical.finalize'))->toBeTrue();
    });
});

test('provisioning pipeline validates duplicate slug and invalid plan', function () {
    Tenant::create([
        'name' => 'Existente',
        'slug' => 'san-lucas',
        'database_name' => 'dummy',
        'status' => 'active',
    ]);

    expect(fn () => $this->pipeline->run([
        'name' => 'Clínica Dental San Lucas Duplicada',
        'slug' => 'san-lucas',
        'domain' => 'sanlucas2.bsdental.test',
        'plan_id' => 'starter',
        'owner_name' => 'Dr. Lucas Morales',
        'owner_email' => 'lucas@sanlucasdental.com',
        'owner_password' => 'SecurePass123!',
    ]))->toThrow(InvalidArgumentException::class);
});
