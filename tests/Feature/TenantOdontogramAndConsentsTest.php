<?php

use App\Core\Auth\Models\User;
use App\Core\Models\ConsentTemplate;
use App\Core\Models\Odontogram;
use App\Core\Models\OdontogramEntry;
use App\Core\Models\Patient;
use App\Core\Models\PatientConsent;
use App\Core\Models\PeriodontalExam;
use App\Core\Models\PeriodontalMeasurement;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\ConsentSigningService;
use App\Core\Services\OdontogramService;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Database\Seeders\PeriodontalDemoSeeder;
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

    $this->dbPathOdo = $this->tenantDatabasePath('tenant_gate_odo_test.sqlite');
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
        grantTenantOwnerAccess($this->user);

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
        'accepted_terms' => true,
    ]);
    $consentResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $consent = PatientConsent::where('patient_id', $this->patient->id)->firstOrFail();
    expect($consent->title)->toBe('Consentimiento Informado para Exodoncia Quirúrgica')
        ->and($consent->template_version)->toBe(1)
        ->and($consent->rendered_content)->toContain('Carlos Castillo')
        ->and($consent->integrity_hash)->not->toBeNull()
        ->and(strlen($consent->integrity_hash))->toBe(64);

    $verification = app(ConsentSigningService::class)->verify($consent);
    expect($verification['status'])->toBe('verified')
        ->and($verification['algorithm'])->toBe('sha256-consent-v2');

    // 5. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'odontogram.entry_created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'consent.signed')->exists())->toBeTrue();
});

test('[GATE ODO] Consent integrity detects immutable snapshot tampering and listing never exposes signature data', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $consent = app(ConsentSigningService::class)->sign(
        $this->patient,
        $this->template,
        'Carlos Castillo',
        'V-18765432',
        'patient',
        'drawn',
        'data:image/png;base64,c2lnbmF0dXJl',
        '127.0.0.1'
    );

    $response = $this->get("http://odontograma.bsdental.test/patients/{$this->patient->id}/consents?consent_id={$consent->id}");
    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clinic/Consents/Index')
            ->where('selectedConsentId', $consent->id)
            ->where('consents.0.integrity.status', 'verified')
            ->missing('consents.0.signature_data')
            ->missing('consents.0.signed_ip'));

    $consent->rendered_content = 'Contenido alterado fuera del flujo de firma.';
    expect(app(ConsentSigningService::class)->verify($consent)['status'])->toBe('mismatch');
});

test('[GATE ODO] Consent signing requires explicit acceptance, a valid private signature and blocks witness templates', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $payload = [
        'consent_template_id' => $this->template->id,
        'signed_by_name' => 'Carlos Castillo',
        'signed_by_identification' => 'V-18765432',
        'relationship' => 'patient',
        'signature_type' => 'drawn',
        'signature_data' => 'firma-fija-no-valida',
    ];

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/consents", $payload)
        ->assertSessionHasErrors(['signature_data', 'accepted_terms']);

    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->template->update(['required_witness' => true]);
    $payload['signature_data'] = 'data:image/png;base64,c2lnbmF0dXJl';
    $payload['accepted_terms'] = true;

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/consents", $payload)
        ->assertSessionHasErrors('error');

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(PatientConsent::where('patient_id', $this->patient->id)->count())->toBe(0);
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

test('[GATE ODO] Legacy condition and surface vocabulary remains readable without rewriting history', function () {
    $entry = new OdontogramEntry;
    $entry->setRawAttributes([
        'condition' => 'resin',
        'surface' => 'occlusal',
        'surfaces' => json_encode(['occlusal']),
    ]);

    expect($entry->condition)->toBe('restored_composite')
        ->and($entry->surface)->toBe('occlusal_incisal')
        ->and($entry->surfaces)->toBe(['occlusal_incisal']);
});

test('[GATE ODO] Structured entries support multiple surfaces, immutable corrections and quote prefill', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
        'tooth_number' => 26,
        'surfaces' => ['mesial', 'occlusal_incisal', 'distal'],
        'condition' => 'caries',
        'entry_type' => 'diagnosis',
        'clinical_status' => 'active',
        'verification_status' => 'confirmed',
        'lifecycle_state' => 'initial_diagnosis',
        'notes' => 'Lesión MOD confirmada.',
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $odontogram = Odontogram::where('patient_id', $this->patient->id)->firstOrFail();
    $original = $odontogram->entries()->firstOrFail();
    expect($original->surfaces)->toBe(['mesial', 'occlusal_incisal', 'distal'])
        ->and($original->entry_type)->toBe('diagnosis');

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/entries", [
        'tooth_number' => 26,
        'surfaces' => ['mesial', 'occlusal_incisal'],
        'condition' => 'caries',
        'entry_type' => 'diagnosis',
        'clinical_status' => 'active',
        'verification_status' => 'confirmed',
        'lifecycle_state' => 'initial_diagnosis',
        'notes' => 'La superficie distal no presenta lesión.',
        'supersedes_entry_id' => $original->id,
        'amendment_reason' => 'Corrección posterior a revisión radiográfica.',
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($odontogram->entries()->count())->toBe(2)
        ->and($original->fresh()->notes)->toBe('Lesión MOD confirmada.')
        ->and($original->corrections()->firstOrFail()->amendment_reason)->toContain('radiográfica');

    $this->get("http://odontograma.bsdental.test/patients/{$this->patient->id}/quotes/create?tooth_number=26&surface=mesial&clinical_note=Lesion%20MOD&odontogram_entry_id={$original->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Clinic/Quotes/Create')
            ->where('prefill.tooth_number', 26)
            ->where('prefill.surface', 'mesial')
            ->where('prefill.odontogram_entry_id', $original->id));
});

test('[GATE ODO] Tooth matrix preserves independent visual state for each surface', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);

    $service = app(OdontogramService::class);
    $odontogram = $service->getOrCreateForPatient($this->patient->id);

    $service->recordClinicalEntry($odontogram, [
        'tooth_number' => 16,
        'surfaces' => ['occlusal_incisal'],
        'condition' => 'caries',
        'lifecycle_state' => 'initial_diagnosis',
    ], $this->user->id);
    $service->recordClinicalEntry($odontogram, [
        'tooth_number' => 16,
        'surfaces' => ['vestibular'],
        'condition' => 'restored_composite',
        'lifecycle_state' => 'completed',
    ], $this->user->id);

    $matrix = $service->getToothMatrix($odontogram);

    expect($matrix[16]['surfaces']['occlusal_incisal']['condition'])->toBe('caries')
        ->and($matrix[16]['surfaces']['vestibular']['condition'])->toBe('restored_composite')
        ->and($matrix[16]['surfaces']['occlusal_incisal']['lifecycle_state'])->toBe('initial_diagnosis')
        ->and($matrix[16]['surfaces']['vestibular']['lifecycle_state'])->toBe('completed');
});

test('[GATE ODO] Periodontal chart stores six-site measurements and caries risk', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $sites = ['mb', 'b', 'db', 'ml', 'l', 'dl'];
    $measurements = array_map(fn (string $site) => [
        'site' => $site,
        'probing_depth' => $site === 'mb' ? 5 : 3,
        'recession' => 0,
        'bleeding' => $site === 'mb',
        'plaque' => false,
        'suppuration' => false,
        'mobility' => 0,
        'furcation' => 0,
        'is_implant' => false,
    ], $sites);

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/periodontal-measurements", [
        'tooth_number' => 16,
        'measurements' => $measurements,
    ])->assertRedirect();

    $this->post("http://odontograma.bsdental.test/patients/{$this->patient->id}/odontogram/caries-risk", [
        'caries_risk_level' => 'high',
        'caries_risk_factors' => ['Caries activa reciente'],
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $exam = PeriodontalExam::with('measurements')->firstOrFail();
    $odontogram = Odontogram::where('patient_id', $this->patient->id)->firstOrFail();
    expect($exam->measurements)->toHaveCount(6)
        ->and($exam->measurements->firstWhere('site', 'mb')->probing_depth)->toBe(5)
        ->and($exam->measurements->firstWhere('site', 'mb')->bleeding)->toBeTrue()
        ->and($odontogram->caries_risk_level)->toBe('high')
        ->and($odontogram->caries_risk_factors)->toBe(['Caries activa reciente']);
});

test('[GATE ODO] Demo periodontal chart provides two teeth without overwriting saved measurements', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    Odontogram::create([
        'patient_id' => $this->patient->id,
        'type' => 'initial',
        'notes' => 'Odontograma inicial de valoración',
    ]);

    app(PeriodontalDemoSeeder::class)->run();

    $exam = PeriodontalExam::with('measurements')->firstOrFail();
    expect($exam->measurements)->toHaveCount(12)
        ->and($exam->measurements->where('tooth_number', 16))->toHaveCount(6)
        ->and($exam->measurements->where('tooth_number', 21))->toHaveCount(6)
        ->and($exam->measurements->firstWhere(fn ($item) => $item->tooth_number === 16 && $item->site === 'db')->probing_depth)->toBe(4)
        ->and($exam->measurements->firstWhere(fn ($item) => $item->tooth_number === 16 && $item->site === 'db')->bleeding)->toBeTrue()
        ->and($exam->measurements->firstWhere(fn ($item) => $item->tooth_number === 21 && $item->site === 'b')->recession)->toBe(1);

    PeriodontalMeasurement::query()
        ->where('periodontal_exam_id', $exam->id)
        ->where('tooth_number', 16)
        ->where('site', 'mb')
        ->update(['probing_depth' => 7]);

    app(PeriodontalDemoSeeder::class)->run();

    expect(PeriodontalMeasurement::query()
        ->where('periodontal_exam_id', $exam->id)
        ->where('tooth_number', 16)
        ->where('site', 'mb')
        ->value('probing_depth'))->toBe(7)
        ->and(PeriodontalMeasurement::count())->toBe(12);
});
