<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\Professional;
use App\Core\Models\Room;
use App\Core\Models\Specialty;
use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
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

    $this->dbPathCore = database_path('tenant_gate_core_test.sqlite');
    if (! file_exists($this->dbPathCore)) {
        touch($this->dbPathCore);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental OdontoCenter',
        'slug' => 'odontocenter',
        'database_name' => $this->dbPathCore,
        'status' => 'active',
        'settings' => [
            'currency' => 'USD',
            'timezone' => 'America/Caracas',
        ],
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'odontocenter.bsdental.test',
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

        $this->owner = User::create([
            'name' => 'Dra. María Odonto Owner',
            'email' => 'maria@odontocenter.com',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->specialty = Specialty::create([
            'name' => 'Ortodoncia',
            'code' => 'ORTO',
            'is_active' => true,
        ]);
    });
});

test('[GATE CORE] Owner can configure clinic settings, create branches, rooms and professionals', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->owner, 'web');

    // 1. Update Clinic Settings
    $settingsResponse = $this->put('http://odontocenter.bsdental.test/settings', [
        'name' => 'OdontoCenter Premium Dental',
        'currency' => 'EUR',
        'timezone' => 'Europe/Madrid',
        'phone' => '+34 91 123 4567',
        'address' => 'Paseo de la Castellana 100, Madrid',
    ]);
    $settingsResponse->assertRedirect();

    $this->tenant->refresh();
    expect($this->tenant->name)->toBe('OdontoCenter Premium Dental')
        ->and($this->tenant->settings['currency'])->toBe('EUR')
        ->and($this->tenant->settings['timezone'])->toBe('Europe/Madrid');

    // 2. Create Branches
    $branchResponse = $this->post('http://odontocenter.bsdental.test/branches', [
        'name' => 'Sede Central Castellana',
        'code' => 'SUC-01',
        'address' => 'Paseo de la Castellana 100',
        'phone' => '+34 91 123 4567',
        'is_main' => true,
    ]);
    $branchResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $branch = Branch::where('code', 'SUC-01')->firstOrFail();
    expect($branch->name)->toBe('Sede Central Castellana')
        ->and($branch->is_main)->toBeTrue();

    // 3. Create Dental Chairs / Rooms in Branch
    $roomResponse = $this->post("http://odontocenter.bsdental.test/branches/{$branch->id}/rooms", [
        'name' => 'Sillón 1 - Cirugía e Implantes',
        'code' => 'SIL-01',
    ]);
    $roomResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $room = Room::where('code', 'SIL-01')->firstOrFail();
    expect($room->branch_id)->toBe($branch->id)
        ->and($branch->rooms()->count())->toBe(1);

    // 4. Create Professional
    $proResponse = $this->post('http://odontocenter.bsdental.test/professionals', [
        'first_name' => 'Alejandro',
        'last_name' => 'Sánchez',
        'license_number' => 'COL-28491',
        'color' => '#0ea5e9',
        'phone' => '+34 600 000 111',
        'email' => 'alejandro.sanchez@odontocenter.com',
        'specialty_ids' => [$this->specialty->id],
        'branch_ids' => [$branch->id],
    ]);
    $proResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $pro = Professional::where('license_number', 'COL-28491')->firstOrFail();
    expect($pro->full_name)->toBe('Alejandro Sánchez')
        ->and($pro->specialties()->count())->toBe(1)
        ->and($pro->branches()->count())->toBe(1);

    // 5. Verify Audit Logs were registered
    expect(TenantAuditLog::where('action', 'branch.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'room.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'professional.created')->exists())->toBeTrue();
});
