<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\Branch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Models\Refund;
use App\Core\Models\Room;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\Warehouse;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\AnalyticsReportingService;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
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

    $this->dbPathAnl = $this->tenantDatabasePath('tenant_gate_anl_test.sqlite');
    if (! file_exists($this->dbPathAnl)) {
        touch($this->dbPathAnl);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Analytics Test',
        'slug' => 'analytics-test',
        'database_name' => $this->dbPathAnl,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'analytics.bsdental.test',
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
            'name' => 'Director Médico Gerencial',
            'email' => 'gerencia@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);

        $this->branch = Branch::create([
            'name' => 'Sede Analytics Central',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sillón Quirúrgico 01',
            'is_active' => true,
        ]);

        $this->professional = Professional::create([
            'first_name' => 'Dr. Fernando',
            'last_name' => 'Implantólogo',
            'license_number' => 'COL-1122',
            'specialty' => 'Implantología',
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00500',
            'first_name' => 'Mariana',
            'last_name' => 'Valero',
            'phone' => '+58 412 555-4433',
            'status' => 'active',
        ]);

        $cat = ProcedureCategory::create(['name' => 'Implantología']);
        $this->procedure = Procedure::create([
            'category_id' => $cat->id,
            'code' => 'IMP-01',
            'name' => 'Implante Dental Titanio',
            'price' => 500.00,
        ]);

        $this->plan = TreatmentPlan::create([
            'patient_id' => $this->patient->id,
            'plan_number' => 'PLN-00500',
            'title' => 'Plan de Rehabilitación Oral',
            'total_amount' => 500.00,
            'executed_amount' => 500.00,
            'progress_percentage' => 100.0,
            'status' => 'in_progress',
        ]);

        $this->planItem = TreatmentPlanItem::create([
            'treatment_plan_id' => $this->plan->id,
            'procedure_id' => $this->procedure->id,
            'phase_number' => 1,
            'tooth_number' => 21,
            'price' => 500.00,
            'status' => 'completed',
            'completed_at' => Carbon::now(),
        ]);
    });
});

test('[GATE-ANL] Full executive analytics reconciliation: production vs collections vs costs vs margin and CSV export', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Create Patient Charge ($500)
    $charge = PatientCharge::create([
        'patient_id' => $this->patient->id,
        'treatment_plan_item_id' => $this->planItem->id,
        'professional_id' => $this->professional->id,
        'charge_number' => 'CHG-00500',
        'concept' => 'Implante Dental Titanio',
        'amount' => 500.00,
        'tax_amount' => 0.00,
        'total_amount' => 500.00,
        'paid_amount' => 400.00,
        'balance_due' => 100.00,
        'status' => 'partially_paid',
        'created_at' => Carbon::now()->subDays(5),
    ]);

    // 2. Record Payment ($400)
    $payment = Payment::create([
        'patient_id' => $this->patient->id,
        'payment_number' => 'REC-00500',
        'total_amount' => 400.00,
        'allocated_amount' => 400.00,
        'unallocated_amount' => 0.00,
        'refunded_amount' => 50.00,
        'status' => 'refunded',
        'paid_at' => Carbon::now(),
    ]);

    // 3. Record Refund ($50)
    Refund::create([
        'payment_id' => $payment->id,
        'patient_id' => $this->patient->id,
        'amount' => 50.00,
        'reason' => 'Ajuste comercial',
        'refunded_at' => Carbon::now(),
    ]);

    // 4. Record Direct Material Cost ($80)
    $invCat = InventoryCategory::create(['name' => 'Implantes y Quirúrgicos']);
    $invItem = InventoryItem::create([
        'category_id' => $invCat->id,
        'sku' => 'IMP-TIT-01',
        'name' => 'Tornillo Implante 3.5mm',
        'unit' => 'unidad',
        'cost_price' => 80.00,
    ]);
    $warehouse = Warehouse::create([
        'branch_id' => $this->branch->id,
        'name' => 'Almacén Quirúrgico',
        'is_main' => true,
    ]);

    StockMovement::create([
        'inventory_item_id' => $invItem->id,
        'warehouse_id' => $warehouse->id,
        'type' => 'consumption',
        'quantity' => 1.0,
        'previous_stock' => 10.0,
        'new_stock' => 9.0,
        'unit_cost' => 80.00,
        'total_cost' => 80.00,
        'created_at' => Carbon::now(),
    ]);

    // 5. Accrue Professional Commission ($150 - 30%)
    ProfessionalCompensation::create([
        'professional_id' => $this->professional->id,
        'patient_charge_id' => $charge->id,
        'rule_type' => 'percentage_production',
        'rate' => 30.00,
        'base_amount' => 500.00,
        'commission_amount' => 150.00,
        'status' => 'accrued',
        'accrued_at' => Carbon::now(),
    ]);

    // 6. Create Completed Appointment (60 min)
    Appointment::create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'room_id' => $this->room->id,
        'professional_id' => $this->professional->id,
        'start_time' => Carbon::now()->subHours(2),
        'end_time' => Carbon::now()->subHours(1),
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);

    // 7. Verify Executive Analytics Service Calculations
    $service = app(AnalyticsReportingService::class);
    $kpis = $service->getExecutiveKpis(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());

    expect($kpis['production'])->toBe(500.0)
        ->and($kpis['gross_collected'])->toBe(400.0)
        ->and($kpis['refunds'])->toBe(50.0)
        ->and($kpis['net_collected'])->toBe(350.0)
        ->and($kpis['receivables'])->toBe(100.0)
        ->and($kpis['direct_material_costs'])->toBe(80.0)
        ->and($kpis['professional_commissions'])->toBe(150.0)
        ->and($kpis['contribution_margin'])->toBe(270.0) // 500 - 80 - 150
        ->and($kpis['net_cash_flow'])->toBe(350.0);

    // 8. Verify Receivables Aging ($100 in 0-30 days bucket)
    $aging = $service->getReceivablesAging();
    expect($aging['current_0_30'])->toBe(100.0)
        ->and($aging['total_receivable'])->toBe(100.0);

    // 9. Verify Chair Occupancy (60 min on room)
    $occupancy = $service->getChairOccupancy(Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth());
    expect($occupancy[0]['occupied_minutes'])->toBe(60)
        ->and($occupancy[0]['total_appointments'])->toBe(1);

    // 10. Verify Export Route
    $exportResponse = $this->get('http://analytics.bsdental.test/analytics/export');
    $exportResponse->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // 11. Verify Audit Logs
    $context->makeCurrent($this->tenant);
    expect(TenantAuditLog::where('action', 'analytics.report_exported')->exists())->toBeTrue();
});
