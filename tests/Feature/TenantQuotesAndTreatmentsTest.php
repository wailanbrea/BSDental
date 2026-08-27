<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Employee;
use App\Core\Models\Patient;
use App\Core\Models\PatientMedicalHistory;
use App\Core\Models\PayrollRun;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Models\Quote;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
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

    $this->dbPathQuo = $this->tenantDatabasePath('tenant_gate_quo_test.sqlite');
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

        $this->professional = Professional::create([
            'user_id' => $this->user->id,
            'first_name' => 'Fernando',
            'last_name' => 'Arancel',
            'license_number' => 'ODO-TEST-01',
            'color' => '#0d9488',
            'is_active' => true,
        ]);

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
    $completeResponse = $this->post("http://presupuestos.bsdental.test/treatment-items/{$item1->id}/complete", [
        'professional_id' => $this->professional->id,
    ]);
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
    $completeResponse2 = $this->post("http://presupuestos.bsdental.test/treatment-items/{$item2->id}/complete", [
        'professional_id' => $this->professional->id,
    ]);
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

test('[FIN PAY] Monthly payroll combines fixed salaries with idempotent production commissions', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $this->post('http://presupuestos.bsdental.test/payroll/employees', [
        'full_name' => 'Laura Recepción',
        'position' => 'Recepcionista',
        'compensation_type' => 'fixed_salary',
        'monthly_salary' => 30000,
    ])->assertRedirect()->assertSessionHas('success', 'Empleado agregado a nómina.');
    $context->makeCurrent($this->tenant);

    $this->post('http://presupuestos.bsdental.test/payroll/employees', [
        'professional_id' => $this->professional->id,
        'full_name' => $this->professional->full_name,
        'position' => 'Odontólogo',
        'compensation_type' => 'commission',
        'commission_rate' => 30,
    ])->assertRedirect()->assertSessionHas('success', 'Empleado agregado a nómina.');
    $context->makeCurrent($this->tenant);
    expect(Employee::where('status', 'active')->count())->toBe(2);

    $plan = TreatmentPlan::create([
        'patient_id' => $this->patient->id,
        'title' => 'Plan para nómina',
        'status' => 'active',
        'total_estimated' => 1000,
        'total_performed' => 0,
        'progress_percentage' => 0,
    ]);
    $item = TreatmentPlanItem::create([
        'treatment_plan_id' => $plan->id,
        'procedure_id' => $this->procResina->id,
        'price' => 1000,
        'status' => 'pending',
    ]);

    app(TreatmentPlanGeneratorService::class)->completeItem(
        $item,
        (string) $this->user->id,
        null,
        $this->professional,
    );

    $compensation = ProfessionalCompensation::where('treatment_plan_item_id', $item->id)->firstOrFail();
    expect($compensation->base_amount)->toBe(1000.0)
        ->and($compensation->rate)->toBe(30.0)
        ->and($compensation->commission_amount)->toBe(300.0);

    $this->get('http://presupuestos.bsdental.test/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Payroll/Index')
            ->where('summary.active_employees', 2));

    $month = now()->format('Y-m');
    $this->post('http://presupuestos.bsdental.test/payroll/runs', ['month' => $month])->assertRedirect();

    $context->makeCurrent($this->tenant);
    $run = PayrollRun::with('items.lines')->firstOrFail();
    expect($run->fixed_salary_total)->toBe(30000.0)
        ->and($run->commission_total)->toBe(300.0)
        ->and($run->net_total)->toBe(30300.0)
        ->and($compensation->fresh()->status)->toBe('settled');

    $this->post("http://presupuestos.bsdental.test/payroll/runs/{$run->id}/pay")->assertRedirect();
    $context->makeCurrent($this->tenant);
    expect($run->fresh()->status)->toBe('paid')
        ->and($compensation->fresh()->status)->toBe('paid');

    $this->post('http://presupuestos.bsdental.test/payroll/runs', ['month' => $month])
        ->assertSessionHasErrors('month');
    $context->makeCurrent($this->tenant);
    expect(PayrollRun::count())->toBe(1)
        ->and(ProfessionalCompensation::where('treatment_plan_item_id', $item->id)->count())->toBe(1);
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

test('[CLN-03] Multi-phased quotes preserve phase structure when converted to active treatment plan', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    // 1. Create quote with 2 distinct phases (Phase 1: Saneamiento, Phase 2: Restauración)
    $createResponse = $this->post("http://presupuestos.bsdental.test/patients/{$this->patient->id}/quotes", [
        'alternative_name' => 'Plan Integral por Fases',
        'items' => [
            ['procedure_id' => $this->procLimpieza->id, 'quantity' => 1, 'phase' => 1],
            ['procedure_id' => $this->procResina->id, 'quantity' => 1, 'tooth_number' => 11, 'phase' => 2],
        ],
    ]);
    $createResponse->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $quote = Quote::where('patient_id', $this->patient->id)->where('alternative_name', 'Plan Integral por Fases')->firstOrFail();
    expect($quote->items()->where('phase', 1)->count())->toBe(1)
        ->and($quote->items()->where('phase', 2)->count())->toBe(1);

    // 2. Approve and convert to treatment plan
    $approveResponse = $this->post("http://presupuestos.bsdental.test/quotes/{$quote->id}/approve");
    $approveResponse->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $plan = TreatmentPlan::where('quote_id', $quote->id)->firstOrFail();
    expect($plan->items()->where('phase', 1)->count())->toBe(1)
        ->and($plan->items()->where('phase', 2)->count())->toBe(1);
});
