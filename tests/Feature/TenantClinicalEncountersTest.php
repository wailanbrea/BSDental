<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\ClinicalEncounter;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Security\Models\TenantAuditLog;
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

    $this->dbPathCl = database_path('tenant_gate_cl_test.sqlite');
    if (! file_exists($this->dbPathCl)) {
        touch($this->dbPathCl);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental Historia Test',
        'slug' => 'historia-test',
        'database_name' => $this->dbPathCl,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'historia.bsdental.test',
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

        $this->user = User::create([
            'name' => 'Dr. Javier Endodoncista',
            'email' => 'javier@historia.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'name' => 'Sede Central',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->professional = Professional::create([
            'first_name' => 'Dr. Javier',
            'last_name' => 'Endodoncista',
            'color' => '#0d9488',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Lucía',
            'last_name' => 'Méndez',
            'phone' => '+58 412 333-4455',
            'status' => 'active',
        ]);
    });
});

test('[GATE CL] Clinical navigation lists all encounters and patient encounters with GET', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->get('http://historia.bsdental.test/encounters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Encounters/Index')
            ->where('patient', null)
        );

    $this->get("http://historia.bsdental.test/patients/{$this->patient->id}/encounters")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Encounters/Index')
            ->where('patient.id', $this->patient->id)
        );
});

test('[GATE CL] Comprehensive clinical encounter lifecycle: draft, finalized inmutability and signed amendments', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Create Draft Clinical Encounter with SOAP, Diagnoses and Prescriptions
    $createResponse = $this->post('http://historia.bsdental.test/encounters', [
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'encounter_date' => now()->toDateTimeString(),
        'chief_complaint' => 'Dolor intenso en pieza 16 con sensibilidad térmica',
        'physical_examination' => 'Caries oclusal profunda en 16 con compromiso pulpar',
        'subjective' => 'Refiere dolor nocturno pulsátil',
        'objective' => 'Percusión vertical positiva',
        'assessment' => 'Pulpitis irreversible sintomática',
        'plan' => 'Tratamiento de conducto (Endodoncia) pieza 16',
        'treatment_performed' => 'Acceso cameral, localización de 3 conductos, instrumentación biomecánica y medicación intraconducto con Ca(OH)2',
        'diagnoses' => [
            ['code' => 'K04.0', 'description' => 'Pulpitis irreversible', 'type' => 'definitive'],
        ],
        'prescriptions' => [
            [
                'medication_name' => 'Ketorolaco',
                'dosage' => '10 mg',
                'frequency' => 'cada 8 horas',
                'duration' => '3 días',
                'instructions' => 'Tomar con abundante agua después de las comidas',
            ],
        ],
    ]);
    $createResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $encounter = ClinicalEncounter::where('patient_id', $this->patient->id)->firstOrFail();
    expect($encounter->status)->toBe('draft')
        ->and($encounter->evolution)->not->toBeNull()
        ->and($encounter->diagnoses)->toHaveCount(1)
        ->and($encounter->prescriptions)->toHaveCount(1)
        ->and($encounter->integrity_hash)->toBeNull();

    // 2. Finalize Encounter (Seal against direct modification)
    $finalizeResponse = $this->post("http://historia.bsdental.test/encounters/{$encounter->id}/finalize");
    $finalizeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $encounter->refresh();
    expect($encounter->status)->toBe('finalized')
        ->and($encounter->finalized_at)->not->toBeNull()
        ->and($encounter->integrity_hash)->not->toBeNull()
        ->and(strlen($encounter->integrity_hash))->toBe(64); // SHA-256

    $originalTreatment = $encounter->evolution->treatment_performed;

    // 3. Attempt direct modification of finalized encounter -> must fail / be rejected
    $updateAttemptResponse = $this->put("http://historia.bsdental.test/encounters/{$encounter->id}", [
        'chief_complaint' => 'Intento de sobreescritura no autorizada',
        'treatment_performed' => 'Texto malicioso alterado',
    ]);
    $updateAttemptResponse->assertSessionHasErrors(['error']);

    $context->makeCurrent($this->tenant);
    $encounter->refresh();
    expect($encounter->evolution->treatment_performed)->toBe($originalTreatment);

    // 4. Clinical Amendment Flow: Add justified signed amendment
    $amendmentResponse = $this->post("http://historia.bsdental.test/encounters/{$encounter->id}/amend", [
        'reason' => 'Corrección: se localizó un cuarto conducto (MV2) antes del sellado provisional',
        'amended_content' => [
            'conductos_tratados' => 'MV1, MV2, DV, P',
            'longitud_trabajo' => 'MV1: 21mm, MV2: 20.5mm, DV: 21mm, P: 22mm',
        ],
    ]);
    $amendmentResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $encounter->refresh();
    expect($encounter->status)->toBe('amended')
        ->and($encounter->amendments)->toHaveCount(1);

    $amendment = $encounter->amendments->first();
    expect($amendment->reason)->toContain('MV2')
        ->and($amendment->integrity_hash)->not->toBeNull()
        ->and($amendment->amended_by_user_id)->toBe($this->user->id);

    // 5. Audit Log verification
    expect(TenantAuditLog::where('action', 'encounter.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'encounter.finalized')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'encounter.amended')->exists())->toBeTrue();
});
