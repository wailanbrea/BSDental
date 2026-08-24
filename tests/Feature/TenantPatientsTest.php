<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Patient;
use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    Storage::fake('local');

    $this->dbPathPat = $this->tenantDatabasePath('tenant_gate_pat_test.sqlite');
    if (! file_exists($this->dbPathPat)) {
        touch($this->dbPathPat);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental Pacientes',
        'slug' => 'clinica-pacientes',
        'database_name' => $this->dbPathPat,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'pacientes.bsdental.test',
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
            'name' => 'Dr. Fernando Odontólogo',
            'email' => 'fernando@pacientes.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);
    });
});

test('[GATE PAT] Comprehensive patient lifecycle: creation, anamnesis, duplicate warning, search, file upload and 360 profile', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Create Patient with Medical Alerts
    $response = $this->post('http://pacientes.bsdental.test/patients', [
        'first_name' => 'Valentina',
        'last_name' => 'Ramos',
        'identification_type' => 'CEDULA',
        'identification_number' => 'V-20111222',
        'birth_date' => '1995-04-12',
        'gender' => 'female',
        'phone' => '+58 412 999-8877',
        'email' => 'valentina.ramos@email.test',
        'city' => 'Caracas',
        'emergency_contact_name' => 'María Ramos',
        'emergency_contact_phone' => '+58 414 100-2000',
        'emergency_contact_relationship' => 'Madre',
        'insurance_company' => 'Seguro Dental Test',
        'insurance_policy_number' => 'POL-2026-001',
        'notes' => 'Prefiere citas en la mañana.',
        'tags' => ['VIP', 'Seguimiento'],
        'blood_type' => 'O+',
        'allergies' => ['Penicilina', 'Látex'],
        'systemic_conditions' => ['Asma'],
        'current_medications' => ['Salbutamol'],
        'is_pregnant' => false,
        'has_pacemaker' => false,
        'medical_notes' => 'Usar anestésico sin penicilina.',
    ]);

    $context->makeCurrent($this->tenant);
    $patient = Patient::where('identification_number', 'V-20111222')->firstOrFail();
    expect($patient->record_number)->toBe('HC-00001')
        ->and($patient->full_name)->toBe('Valentina Ramos')
        ->and($patient->medicalHistory)->not->toBeNull()
        ->and($patient->medicalHistory->allergies)->toContain('Penicilina');

    $response->assertRedirect("http://pacientes.bsdental.test/patients/{$patient->id}");

    // 2. Duplicate Detection Endpoint Warning
    $dupResponse = $this->getJson('http://pacientes.bsdental.test/patients/check-duplicates?identification_number=V-20111222');
    $dupResponse->assertOk();
    $candidates = $dupResponse->json('candidates');
    expect($candidates)->toHaveCount(1)
        ->and($candidates[0]['record_number'])->toBe('HC-00001');

    // 3. Server-side Search by Name and Document
    $searchResponse = $this->get('http://pacientes.bsdental.test/patients?search=Valentina');
    $searchResponse->assertOk();

    // 4. View 360 Profile
    $showResponse = $this->get("http://pacientes.bsdental.test/patients/{$patient->id}");
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Patients/Show')
            ->where('auth.user.name', 'Dr. Fernando Odontólogo')
            ->where('clinic.name', 'Clínica Dental Pacientes'));

    // 5. Edit Patient with preloaded medical history
    $editResponse = $this->get("http://pacientes.bsdental.test/patients/{$patient->id}/edit");
    $editResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Patients/Create')
            ->where('patient.id', $patient->id)
            ->where('patient.city', 'Caracas')
            ->where('patient.emergency_contact_name', 'María Ramos')
            ->where('patient.insurance_policy_number', 'POL-2026-001')
            ->where('patient.tags.0', 'VIP')
            ->where('patient.medical_history.allergies.0', 'Penicilina'));

    $updateResponse = $this->put("http://pacientes.bsdental.test/patients/{$patient->id}", [
        'first_name' => 'Valentina María',
        'last_name' => 'Ramos',
        'identification_type' => 'CEDULA',
        'identification_number' => 'V-20111222',
        'phone' => '+58 412 999-8877',
        'email' => 'valentina.actualizada@email.test',
        'city' => 'Valencia',
        'emergency_contact_name' => 'María Ramos',
        'emergency_contact_phone' => '+58 414 100-2000',
        'emergency_contact_relationship' => 'Madre',
        'insurance_company' => 'Seguro Dental Test',
        'insurance_policy_number' => 'POL-2026-002',
        'notes' => 'Preferencia actualizada.',
        'tags' => ['VIP', 'Ortodoncia'],
        'allergies' => ['Penicilina', 'Látex'],
        'systemic_conditions' => ['Asma'],
        'current_medications' => ['Salbutamol'],
        'is_pregnant' => false,
        'bleeding_disorders' => false,
        'has_pacemaker' => false,
        'medical_notes' => 'Anamnesis actualizada.',
    ]);
    $updateResponse->assertRedirect("http://pacientes.bsdental.test/patients/{$patient->id}");

    $context->makeCurrent($this->tenant);
    $patient->refresh();
    expect($patient->first_name)->toBe('Valentina María')
        ->and($patient->email)->toBe('valentina.actualizada@email.test')
        ->and($patient->city)->toBe('Valencia')
        ->and($patient->insurance_policy_number)->toBe('POL-2026-002')
        ->and($patient->tags)->toBe(['VIP', 'Ortodoncia'])
        ->and($patient->medicalHistory->medical_notes)->toBe('Anamnesis actualizada.')
        ->and(TenantAuditLog::where('action', 'patient.updated')->exists())->toBeTrue();

    // 6. Upload Clinical File / Radiography
    $file = UploadedFile::fake()->image('panoramica_inicial.png', 800, 600);
    $uploadResponse = $this->post("http://pacientes.bsdental.test/patients/{$patient->id}/files", [
        'category' => 'radiography',
        'file' => $file,
        'notes' => 'Radiografía panorámica inicial previa a ortodoncia',
    ]);
    $uploadResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $patientFile = $patient->files()->firstOrFail();
    expect($patient->files()->count())->toBe(1)
        ->and($patientFile->category)->toBe('radiography');

    $this->get("http://pacientes.bsdental.test/patients/{$patient->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('patient.files.0.id', $patientFile->id)
            ->missing('patient.files.0.filename')
            ->missing('patient.files.0.stored_path'));

    $viewFileResponse = $this->get("http://pacientes.bsdental.test/patient-files/{$patientFile->id}/view");
    $viewFileResponse->assertOk()->assertHeader('content-type', 'image/png');
    expect($viewFileResponse->headers->get('content-disposition'))->toContain('inline');

    $downloadResponse = $this->get("http://pacientes.bsdental.test/patient-files/{$patientFile->id}/download");
    $downloadResponse->assertOk();
    expect($downloadResponse->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain('panoramica_inicial.png');

    $context->makeCurrent($this->tenant);
    expect(TenantAuditLog::where('action', 'patient.file_viewed')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'patient.file_downloaded')->exists())->toBeTrue();

    // 7. Soft Delete with Audit Log
    $deleteResponse = $this->delete("http://pacientes.bsdental.test/patients/{$patient->id}");
    $deleteResponse->assertRedirect('http://pacientes.bsdental.test/patients');

    $context->makeCurrent($this->tenant);
    expect(Patient::count())->toBe(0)
        ->and(Patient::withTrashed()->count())->toBe(1)
        ->and(TenantAuditLog::where('action', 'patient.deleted')->exists())->toBeTrue();
});

test('[GATE PAT] Admission form exposes the clinical component and requires conditional safety fields', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    $this->get('http://pacientes.bsdental.test/patients/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Patients/Create')
            ->where('suggestedRecordNumber', 'HC-00001')
            ->missing('patient'));

    $this->post('http://pacientes.bsdental.test/patients', [
        'first_name' => 'Paciente',
        'last_name' => 'Condicional',
        'is_minor' => true,
        'is_pregnant' => true,
    ])->assertSessionHasErrors([
        'guardian_name',
        'guardian_phone',
        'pregnancy_weeks',
    ]);

    $context->makeCurrent($this->tenant);
    expect(Patient::count())->toBe(0);
});

test('[CLN-01] Patient merge transfers appointments, charges and soft-deletes duplicate with audit', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $master = Patient::create([
        'record_number' => 'HC-00100',
        'first_name' => 'Carlos',
        'last_name' => 'Gómez',
        'phone' => '+58 412 111-2233',
        'status' => 'active',
        'tags' => ['VIP'],
    ]);

    $duplicate = Patient::create([
        'record_number' => 'HC-00101',
        'first_name' => 'Carlos A.',
        'last_name' => 'Gómez',
        'phone' => '+58 412 111-2233',
        'status' => 'active',
        'tags' => ['Ortodoncia'],
    ]);

    // Create a charge for the duplicate
    $charge = \App\Core\Models\PatientCharge::create([
        'patient_id' => $duplicate->id,
        'charge_number' => 'CHG-99901',
        'concept' => 'Consulta de Urgencia',
        'amount' => 50.00,
        'tax_amount' => 0.00,
        'total_amount' => 50.00,
        'paid_amount' => 0.00,
        'adjusted_amount' => 0.00,
        'balance_due' => 50.00,
        'status' => 'pending',
    ]);

    // Merge duplicate into master
    $mergeResponse = $this->post("http://pacientes.bsdental.test/patients/{$master->id}/merge", [
        'duplicate_patient_id' => $duplicate->id,
        'reason' => 'Registro duplicado creado por error en recepción turno matutino',
    ]);
    $mergeResponse->assertRedirect("http://pacientes.bsdental.test/patients/{$master->id}");

    $context->makeCurrent($this->tenant);
    $master->refresh();
    $charge->refresh();

    expect($charge->patient_id)->toBe($master->id)
        ->and($master->tags)->toContain('VIP')
        ->and($master->tags)->toContain('Ortodoncia')
        ->and(Patient::where('id', $duplicate->id)->exists())->toBeFalse()
        ->and(Patient::withTrashed()->where('id', $duplicate->id)->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'patient.merged')->exists())->toBeTrue();
});
