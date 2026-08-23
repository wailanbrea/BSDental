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

    $this->dbPathPat = database_path('tenant_gate_pat_test.sqlite');
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
        'blood_type' => 'O+',
        'allergies' => ['Penicilina', 'Látex'],
        'systemic_conditions' => ['Asma'],
        'current_medications' => ['Salbutamol'],
        'is_pregnant' => false,
        'has_pacemaker' => false,
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
            ->where('patient.medical_history.allergies.0', 'Penicilina'));

    $updateResponse = $this->put("http://pacientes.bsdental.test/patients/{$patient->id}", [
        'first_name' => 'Valentina María',
        'last_name' => 'Ramos',
        'identification_type' => 'CEDULA',
        'identification_number' => 'V-20111222',
        'phone' => '+58 412 999-8877',
        'email' => 'valentina.actualizada@email.test',
        'allergies' => ['Penicilina', 'Látex'],
        'systemic_conditions' => ['Asma'],
        'current_medications' => ['Salbutamol'],
        'is_pregnant' => false,
        'bleeding_disorders' => false,
        'has_pacemaker' => false,
    ]);
    $updateResponse->assertRedirect("http://pacientes.bsdental.test/patients/{$patient->id}");

    $context->makeCurrent($this->tenant);
    $patient->refresh();
    expect($patient->first_name)->toBe('Valentina María')
        ->and($patient->email)->toBe('valentina.actualizada@email.test')
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
    expect($patient->files()->count())->toBe(1)
        ->and($patient->files()->first()->category)->toBe('radiography');

    // 7. Soft Delete with Audit Log
    $deleteResponse = $this->delete("http://pacientes.bsdental.test/patients/{$patient->id}");
    $deleteResponse->assertRedirect('http://pacientes.bsdental.test/patients');

    $context->makeCurrent($this->tenant);
    expect(Patient::count())->toBe(0)
        ->and(Patient::withTrashed()->count())->toBe(1)
        ->and(TenantAuditLog::where('action', 'patient.deleted')->exists())->toBeTrue();
});
