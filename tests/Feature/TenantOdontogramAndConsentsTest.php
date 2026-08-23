<?php

use App\Core\Auth\Models\User;
use App\Core\Models\ConsentTemplate;
use App\Core\Models\Odontogram;
use App\Core\Models\Patient;
use App\Core\Models\PatientConsent;
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

    $this->dbPathOdo = database_path('tenant_gate_odo_test.sqlite');
    if (! file_exists($this->dbPathOdo)) {
        touch($this->dbPathOdo);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Odontograma Test',
        'slug' => 'odontograma-test',
        'database_name' => $this->dbPathOdo,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'odontograma.bsdental.test',
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
            'name' => 'Dra. Gabriela Odontóloga',
            'email' => 'gabriela@odontograma.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Carlos',
            'last_name' => 'Castillo',
            'phone' => '+58 424 555-6677',
            'status' => 'active',
        ]);

        $this->template = ConsentTemplate::create([
            'slug' => 'extraccion-terceros-molares',
            'title' => 'Consentimiento Informado para Exodoncia Quirúrgica',
            'version' => 1,
            'content' => 'Yo, {{patient_name}}, con HC {{record_number}}, autorizo el procedimiento el día {{date}}...',
            'is_active' => true,
        ]);
    });
});

test('[GATE ODO] Comprehensive structured odontogram lifecycle, tooth-surface transitions and sealed informed consents', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Initial Diagnostic Entry (Pieza 16 - Caries Oclusal)
    $entry1Response = $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
        'tooth_number' => 16,
        'surface' => 'occlusal_incisal',
        'condition' => 'caries',
        'lifecycle_state' => 'initial_diagnosis',
        'notes' => 'Caries de esmalte y dentina',
    ]);
    $entry1Response->assertRedirect();

    $context->makeCurrent($this->tenant);
    $odontogram = Odontogram::where('patient_id', $this->patient->id)->firstOrFail();
    expect($odontogram->entries()->count())->toBe(1);

    // 2. Planning Entry (Pieza 16 - Planificado Resina)
    $entry2Response = $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
        'tooth_number' => 16,
        'surface' => 'occlusal_incisal',
        'condition' => 'restored_composite',
        'lifecycle_state' => 'planned',
        'notes' => 'Presupuestada restauración con resina estética',
    ]);
    $entry2Response->assertRedirect();

    // 3. Execution / Completed Entry (Pieza 16 - Resina Realizada)
    $entry3Response = $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
        'tooth_number' => 16,
        'surface' => 'occlusal_incisal',
        'condition' => 'restored_composite',
        'lifecycle_state' => 'completed',
        'notes' => 'Obturación realizada con resina 3M Filtek Z350',
    ]);
    $entry3Response->assertRedirect();

    $context->makeCurrent($this->tenant);
    expect($odontogram->entries()->count())->toBe(3);

    // Verify view render
    $odoShowResponse = $this->get("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram");
    $odoShowResponse->assertOk();

    // 4. Informed Consent Signing & Sealing
    $consentResponse = $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/consents", [
        'consent_template_id' => $this->template->id,
        'signed_by_name' => 'Carlos Castillo',
        'signed_by_identification' => 'V-18765432',
        'relationship' => 'patient',
        'signature_type' => 'drawn',
        'signature_data' => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iNDAiPjxwYXRoIGQ9Ik0xMCAyMCBRIDUwIDUgOTAgMjAiIHN0cm9rZT0iYmxhY2siIGZpbGw9InRyYW5zcGFyZW50Ii8+PC9zdmc+',
    ]);
    $consentResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $consent = PatientConsent::where('patient_id', $this->patient->id)->firstOrFail();
    expect($consent->title)->toBe('Consentimiento Informado para Exodoncia Quirúrgica')
        ->and($consent->template_version)->toBe(1)
        ->and($consent->rendered_content)->toContain('Carlos Castillo')
        ->and($consent->integrity_hash)->not->toBeNull()
        ->and(strlen($consent->integrity_hash))->toBe(64);

    // 5. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'odontogram.entry_created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'consent.signed')->exists())->toBeTrue();
});

test('[GATE ODO] Odontogram only accepts valid permanent and primary FDI tooth numbers', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    foreach ([19, 49, 56, 86] as $invalidTooth) {
        $this->from("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram")
            ->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
                'tooth_number' => $invalidTooth,
                'surface' => 'all',
                'condition' => 'healthy',
                'lifecycle_state' => 'initial_diagnosis',
            ])
            ->assertRedirect("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram")
            ->assertSessionHasErrors('tooth_number');
    }

    foreach ([11, 48, 51, 85] as $validTooth) {
        $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
            'tooth_number' => $validTooth,
            'surface' => 'all',
            'condition' => 'healthy',
            'lifecycle_state' => 'initial_diagnosis',
        ])->assertRedirect();
    }

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(Odontogram::where('patient_id', $this->patient->id)->firstOrFail()->entries()->count())->toBe(4);
});
