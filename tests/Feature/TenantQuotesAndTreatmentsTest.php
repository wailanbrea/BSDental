<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Patient;
use App\Core\Models\PatientMedicalHistory;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Quote;
use App\Core\Models\TreatmentPlan;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\TreatmentPlanGeneratorService;
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

    $this->dbPathQuo = database_path('tenant_gate_quo_test.sqlite');
    if (! file_exists($this->dbPathQuo)) {
        touch($this->dbPathQuo);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Presupuestos Test',
        'slug' => 'presupuestos-test',
        'database_name' => $this->dbPathQuo,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'presupuestos.bsdental.test',
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
            'name' => 'Dr. Fernando Arancel',
            'email' => 'fernando@presupuestos.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Elena',
            'last_name' => 'Gómez',
            'phone' => '+58 412 555-8899',
            'status' => 'active',
        ]);

        $this->category = ProcedureCategory::create([
            'name' => 'Operatoria y Estética',
            'color' => '#0d9488',
        ]);

        $this->procResina = Procedure::create([
            'category_id' => $this->category->id,
            'code' => 'OP-01',
            'name' => 'Restauración Resina Simple',
            'price' => 45.00,
            'estimated_minutes' => 40,
            'is_active' => true,
        ]);

        $this->procLimpieza = Procedure::create([
            'category_id' => $this->category->id,
            'code' => 'PREV-01',
            'name' => 'Profilaxis y Destartraje',
            'price' => 30.00,
            'estimated_minutes' => 30,
            'is_active' => true,
        ]);
    });
});

test('[GATE QUO] Full lifecycle: procedure catalog, quote calculation, approval without duplication, treatment plan execution and progress metrics', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Create a Draft Quote with 2 items (Pz 16 resina con 10% descuento y profilaxis general)
    $createResponse = $this->post("http://presupuestos.bsdental.test/patients/{$this->patient->id}/quotes", [
        'alternative_name' => 'Plan Estético Recomendado',
        'items' => [
            [
                'procedure_id' => $this->procResina->id,
                'tooth_number' => 16,
                'surface' => 'occlusal_incisal',
                'quantity' => 1,
                'discount_percentage' => 10, // 45.00 - 10% = 40.50
            ],
            [
                'procedure_id' => $this->procLimpieza->id,
                'quantity' => 1,
                'discount_percentage' => 0, // 30.00
            ],
        ],
    ]);
    $createResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $quote = Quote::where('patient_id', $this->patient->id)->firstOrFail();
    expect($quote->quote_number)->toBe('PRE-00001')
        ->and($quote->status)->toBe('draft')
        ->and($quote->items()->count())->toBe(2)
        ->and($quote->subtotal)->toBe(75.0) // 45 + 30
        ->and($quote->discount_total)->toBe(4.5) // 4.5
        ->and($quote->grand_total)->toBe(70.5); // 40.5 + 30

    // 2. View Quote
    $showResponse = $this->get("http://presupuestos.bsdental.test/quotes/{$quote->id}");
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Quotes/Show')
            ->where('quote.quote_number', 'PRE-00001')
            ->where('quote.treatment_plan', null));

    // 3. Approve Quote -> Generates Treatment Plan
    $approveResponse = $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/approve", [
        'approved_by_name' => 'Elena Gómez',
    ]);
    $approveResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $quote->refresh();
    expect($quote->status)->toBe('converted');

    $plan = TreatmentPlan::where('quote_id', $quote->id)->firstOrFail();
    expect($plan->status)->toBe('active')
        ->and($plan->total_estimated)->toBe(70.5)
        ->and($plan->total_performed)->toBe(0.0)
        ->and($plan->progress_percentage)->toBe(0.0)
        ->and($plan->items()->count())->toBe(2);

    $showConvertedResponse = $this->get("http://presupuestos.bsdental.test/quotes/{$quote->id}");
    $showConvertedResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('quote.status', 'converted')
            ->where('quote.treatment_plan.id', $plan->id));

    // 4. Attempting to approve again must NOT duplicate the plan or revert quote status
    $repeatApproval = $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/approve", [
        'approved_by_name' => 'Elena Gómez',
    ]);
    $repeatApproval->assertRedirect();

    $context->makeCurrent($this->tenant);
    $quote->refresh();
    expect($quote->status)->toBe('converted')
        ->and(TreatmentPlan::where('quote_id', $quote->id)->count())->toBe(1);

    $planService = app(TreatmentPlanGeneratorService::class);
    $duplicatePlan = $planService->generateFromQuote($quote, (string) $this->user->id);
    expect($duplicatePlan->id)->toBe($plan->id)
        ->and(TreatmentPlan::where('quote_id', $quote->id)->count())->toBe(1);

    // 5. Execute 1st Treatment Item (Resina Pz 16)
    $item1 = $plan->items()->where('tooth_number', 16)->firstOrFail();
    $completeResponse = $this->post("http://presupuestos.bsdental.test/treatment-items/{$item1->id}/complete");
    $completeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $plan->refresh();
    $item1->refresh();

    expect($item1->status)->toBe('completed')
        ->and($plan->progress_percentage)->toBe(50.0)
        ->and($plan->total_performed)->toBe(40.5)
        ->and($plan->status)->toBe('active');

    // 6. Execute 2nd Treatment Item (Profilaxis) -> Reaches 100%
    $item2 = $plan->items()->whereNull('tooth_number')->firstOrFail();
    $completeResponse2 = $this->post("http://presupuestos.bsdental.test/treatment-items/{$item2->id}/complete");
    $completeResponse2->assertRedirect();

    $context->makeCurrent($this->tenant);
    $plan->refresh();
    $item2->refresh();

    expect($item2->status)->toBe('completed')
        ->and($plan->progress_percentage)->toBe(100.0)
        ->and($plan->total_performed)->toBe(70.5)
        ->and($plan->status)->toBe('completed');

    // 7. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'quote.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'quote.approved')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'treatment_item.completed')->exists())->toBeTrue();
});

test('[GATE QUO] Quote items only accept valid permanent and primary FDI tooth numbers', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $response = $this->post("http://presupuestos.bsdental.test/patients/{$this->patient->id}/quotes", [
        'alternative_name' => 'Presupuesto con pieza inválida',
        'items' => [[
            'procedure_id' => $this->procResina->id,
            'tooth_number' => 19,
            'surface' => 'occlusal_incisal',
            'quantity' => 1,
            'discount_percentage' => 0,
        ]],
    ]);

    $response->assertSessionHasErrors('items.0.tooth_number');

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(Quote::where('patient_id', $this->patient->id)->count())->toBe(0);
});

test('[GATE QUO] A prospect quote becomes approvable only after creating its patient record', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->get('http://presupuestos.bsdental.test/quotes/quick-create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Quotes/Create')
            ->where('mode', 'prospect')
            ->where('patient', null));

    $this->post('http://presupuestos.bsdental.test/quotes/quick', [
        'prospect_first_name' => 'María',
        'prospect_last_name' => 'Prospecto',
        'prospect_phone' => '809-555-0199',
        'alternative_name' => 'Rehabilitación inicial',
        'items' => [[
            'procedure_id' => $this->procLimpieza->id,
            'quantity' => 1,
        ]],
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $quote = Quote::whereNull('patient_id')->firstOrFail();
    expect($quote->prospect_first_name)->toBe('María')
        ->and($quote->items()->count())->toBe(1)
        ->and(TenantAuditLog::where('action', 'quote.quick_created')->exists())->toBeTrue();

    $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/approve")
        ->assertSessionHas('error');

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(TreatmentPlan::where('quote_id', $quote->id)->exists())->toBeFalse();

    $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/convert-to-patient", [
        'first_name' => 'María',
        'last_name' => 'Prospecto',
        'phone' => '809-555-0199',
        'email' => 'maria.prospecto@example.test',
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $quote->refresh();
    $patient = Patient::findOrFail($quote->patient_id);
    expect($patient->record_number)->toBe('HC-00002')
        ->and($patient->source)->toBe('quick_quote')
        ->and(PatientMedicalHistory::where('patient_id', $patient->id)->exists())->toBeTrue()
        ->and($quote->prospect_first_name)->toBe('María');

    $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/approve")
        ->assertRedirect();
    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(TreatmentPlan::where('quote_id', $quote->id)->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'quote.prospect_converted')->exists())->toBeTrue();
});

test('[GATE QUO] A prospect quote can link to a duplicate candidate without creating another patient', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->post('http://presupuestos.bsdental.test/quotes/quick', [
        'prospect_first_name' => 'Elena',
        'prospect_last_name' => 'Gómez',
        'prospect_phone' => '+58 412 555-8899',
        'alternative_name' => 'Alternativa para paciente existente',
        'items' => [['procedure_id' => $this->procResina->id, 'quantity' => 1]],
    ]);

    app(TenantContext::class)->makeCurrent($this->tenant);
    $quote = Quote::whereNull('patient_id')->firstOrFail();
    $this->get("http://presupuestos.bsdental.test/quotes/{$quote->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('prospectCandidates.0.id', $this->patient->id));

    $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/convert-to-patient", [
        'existing_patient_id' => $this->patient->id,
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect(Patient::count())->toBe(1)
        ->and($quote->fresh()->patient_id)->toBe($this->patient->id)
        ->and(TenantAuditLog::where('action', 'quote.prospect_linked')->exists())->toBeTrue();

    $this->get('http://presupuestos.bsdental.test/quotes')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Quotes/AllIndex')
            ->where('quotes.data.0.id', $quote->id));
});
