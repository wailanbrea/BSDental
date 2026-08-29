<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\ClinicalEncounter;
use App\Core\Models\DentalLaboratory;
use App\Core\Models\FollowUpTask;
use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\ProcedureMaterialRule;
use App\Core\Models\Professional;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentExecution;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\UserNotification;
use App\Core\Models\Warehouse;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\DentalLabService;
use App\Core\Services\InventoryStockService;
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

    $this->dbPathInv = $this->tenantDatabasePath('tenant_gate_inv_test.sqlite');
    if (! file_exists($this->dbPathInv)) {
        touch($this->dbPathInv);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Inventario y Lab Test',
        'slug' => 'inventario-test',
        'database_name' => $this->dbPathInv,
        'status' => 'active',
    ]);
    grantTenantModules($this->tenant, ['inventory', 'lab']);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'inventario.bsdental.test',
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
            'name' => 'Dr. Stock Manager',
            'email' => 'stock@inventario.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);

        $this->branch = Branch::create([
            'name' => 'Sede Central Dental',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::create([
            'branch_id' => $this->branch->id,
            'name' => 'Almacén Principal de Materiales',
            'is_main' => true,
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Manuel',
            'last_name' => 'Morales',
            'phone' => '+58 414 555-1234',
            'status' => 'active',
        ]);

        $this->professional = Professional::create([
            'user_id' => $this->user->id,
            'first_name' => 'Dr. Stock',
            'last_name' => 'Manager',
            'color' => '#0d9488',
            'is_active' => true,
        ]);

        $this->invCat = InventoryCategory::create([
            'name' => 'Materiales Restauradores',
        ]);

        $this->itemResina = InventoryItem::create([
            'category_id' => $this->invCat->id,
            'sku' => 'RES-3M-A2',
            'name' => 'Resina Filtek Z350 XT A2',
            'unit' => 'syringe',
            'min_stock' => 2.00,
            'cost_price' => 25.00,
            'is_active' => true,
        ]);

        $this->procCat = ProcedureCategory::create([
            'name' => 'Rehabilitación Oral',
        ]);

        $this->procCorona = Procedure::create([
            'category_id' => $this->procCat->id,
            'code' => 'PROT-01',
            'name' => 'Corona de Porcelana sobre Metal',
            'price' => 180.00,
            'estimated_minutes' => 60,
            'requires_lab' => true,
            'is_active' => true,
        ]);

        $this->procResina = Procedure::create([
            'category_id' => $this->procCat->id,
            'code' => 'REST-01',
            'name' => 'Restauración Resina Simple',
            'price' => 50.00,
            'estimated_minutes' => 30,
            'requires_lab' => false,
            'is_active' => true,
        ]);

        ProcedureMaterialRule::create([
            'procedure_id' => $this->procResina->id,
            'inventory_item_id' => $this->itemResina->id,
            'quantity_required' => 0.25, // Consume 0.25 de jeringa
        ]);

        $this->lab = DentalLaboratory::create([
            'name' => 'Laboratorio Dental Estético Caracas',
            'contact_person' => 'Técnico Dental Juan Pérez',
            'phone' => '+58 212 999-8877',
            'email' => 'juan@labestetico.test',
            'is_active' => true,
        ]);
    });
});

test('[GATE INV] Reconciliable stock ledger, purchase != consumption and dental lab order lifecycle with cost separation', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Purchase 2 Batches of Resin (Batch 1: 5 syringes expiring sooner, Batch 2: 10 syringes expiring later)
    $purchase1 = $this->post('http://inventario.bsdental.test/inventory/purchases', [
        'inventory_item_id' => $this->itemResina->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_number' => 'LOT-2026-001',
        'quantity' => 5.00,
        'cost_per_unit' => 24.00,
        'expires_at' => '2027-01-01',
    ]);
    $purchase1->assertRedirect();

    $purchase2 = $this->post('http://inventario.bsdental.test/inventory/purchases', [
        'inventory_item_id' => $this->itemResina->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_number' => 'LOT-2026-002',
        'quantity' => 10.00,
        'cost_per_unit' => 25.00,
        'expires_at' => '2028-06-30',
    ]);
    $purchase2->assertRedirect();

    $context->makeCurrent($this->tenant);
    $this->itemResina->refresh();
    expect($this->itemResina->totalStock())->toBe(15.0)
        ->and(InventoryBatch::where('inventory_item_id', $this->itemResina->id)->count())->toBe(2)
        ->and(StockMovement::where('type', 'purchase_in')->count())->toBe(2);

    // 2. Consume Materials for a Treatment Plan Procedure (0.25 syringes deducted via FIFO)
    $plan = TreatmentPlan::create([
        'patient_id' => $this->patient->id,
        'title' => 'Plan de Restauraciones',
        'status' => 'active',
        'total_estimated' => 50.00,
    ]);

    $planItem = TreatmentPlanItem::create([
        'treatment_plan_id' => $plan->id,
        'procedure_id' => $this->procResina->id,
        'tooth_number' => 21,
        'price' => 50.00,
        'status' => 'completed',
    ]);

    $stockService = app(InventoryStockService::class);
    $encounter = ClinicalEncounter::create([
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'encounter_date' => now(),
        'status' => 'draft',
    ]);
    $execution = TreatmentExecution::create([
        'treatment_plan_item_id' => $planItem->id,
        'clinical_encounter_id' => $encounter->id,
        'professional_id' => $this->professional->id,
        'executed_by_user_id' => $this->user->id,
        'executed_at' => now(),
    ]);
    $movements = $stockService->consumeMaterialsForProcedure($planItem, $this->warehouse, $execution, (string) $this->user->id);

    expect(count($movements))->toBe(1)
        ->and($movements[0]->type)->toBe('procedure_consumption')
        ->and($movements[0]->quantity)->toBe(-0.25);

    $this->itemResina->refresh();
    expect($this->itemResina->totalStock())->toBe(14.75);

    // 3. Idempotent consumption: consuming again for same plan item must not duplicate deduction
    $duplicateMovements = $stockService->consumeMaterialsForProcedure($planItem, $this->warehouse, $execution, (string) $this->user->id);
    expect(count($duplicateMovements))->toBe(1)
        ->and($this->itemResina->totalStock())->toBe(14.75);

    // 4. Dental Laboratory Order Flow (Corona Pz 16)
    $orderResponse = $this->post('http://inventario.bsdental.test/lab/orders', [
        'patient_id' => $this->patient->id,
        'laboratory_id' => $this->lab->id,
        'tooth_number' => 16,
        'work_description' => 'Corona Porcelana sobre Metal',
        'shade_guide' => 'A2 VITA',
        'estimated_cost' => 45.00,
        'due_date' => '2026-09-05',
    ]);
    $orderResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $order = LabOrder::where('patient_id', $this->patient->id)->firstOrFail();
    expect($order->order_number)->toBe('LAB-00001')
        ->and($order->status)->toBe('draft')
        ->and($order->estimated_cost)->toBe(45.0)
        ->and($order->final_cost)->toBe(0.0)
        ->and($order->payable_status)->toBe('unpaid');

    // 5. A ready lab order creates one internal alert for its owner, without duplicates.
    foreach (['ordered', 'sent', 'in_progress', 'ready'] as $status) {
        $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", ['status' => $status])->assertRedirect();
    }
    $readyResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", ['status' => 'ready']);
    $readyResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    expect(UserNotification::where('user_id', $this->user->id)
        ->where('type', 'lab')
        ->where('severity', 'success')
        ->where('action_url', '/lab')
        ->count())->toBe(1);

    $duplicateReadyResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", [
        'status' => 'ready',
    ]);
    $duplicateReadyResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    expect(UserNotification::where('user_id', $this->user->id)
        ->where('type', 'lab')
        ->count())->toBe(1);

    // 6. Transition order to 'received' with recognized final cost (Costo != Pago)
    $statusResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", [
        'status' => 'received',
        'final_cost' => 50.00, // Recognized cost increased
    ]);
    $statusResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $order->refresh();
    expect($order->status)->toBe('received')
        ->and($order->final_cost)->toBe(50.0)
        ->and($order->payable_status)->toBe('unpaid'); // Cost recognized but not yet paid

    // 7. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'inventory.purchase_recorded')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'lab_order.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'lab_order.status_updated')->exists())->toBeTrue();
});

test('[INV-01 & INV-02] Manual stock adjustments, Kardex ledger and expiry alerts', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $stockService = app(InventoryStockService::class);

    // Initial purchase
    $stockService->recordPurchase(
        $this->itemResina,
        $this->warehouse,
        'LOT-KARDEX-01',
        10.00,
        25.00,
        now()->addDays(15)->format('Y-m-d'),
        (string) $this->user->id
    );

    // 1. Manual adjustment out (waste/rotura)
    $adjustOutResponse = $this->post('http://inventario.bsdental.test/inventory/adjustments', [
        'inventory_item_id' => $this->itemResina->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'waste_loss',
        'quantity' => 2.00,
        'reason' => 'Jeringa dañada accidentalmente en esterilización',
    ]);
    $adjustOutResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $this->itemResina->refresh();
    expect($this->itemResina->totalStock())->toBe(8.0)
        ->and(TenantAuditLog::where('action', 'inventory.adjustment_recorded')->exists())->toBeTrue();

    // 2. Kardex endpoint
    $kardexResponse = $this->get("http://inventario.bsdental.test/inventory/items/{$this->itemResina->id}/kardex");
    $kardexResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Inventory/Kardex')
            ->where('kardex.item.id', $this->itemResina->id)
            ->where('kardex.total_stock', 8)
            ->has('kardex.movements', 2)
        );

    // 3. Alerts (Expiry < 30 days)
    $context->makeCurrent($this->tenant);
    $alerts = $stockService->getInventoryAlerts($this->warehouse);
    expect($alerts['expiring_count'])->toBeGreaterThanOrEqual(1);
});

test('[TREATMENT EXECUTION] Completion is encounter-backed, inventory-safe, and creates execution follow-ups', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $plan = TreatmentPlan::create([
        'patient_id' => $this->patient->id,
        'title' => 'Plan con material insuficiente',
        'status' => 'active',
        'total_estimated' => 50.00,
    ]);
    $item = TreatmentPlanItem::create([
        'treatment_plan_id' => $plan->id,
        'procedure_id' => $this->procResina->id,
        'price' => 50.00,
        'status' => 'pending',
    ]);
    $encounter = ClinicalEncounter::create([
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'encounter_date' => now(),
        'status' => 'draft',
    ]);

    $this->post("http://inventario.bsdental.test/treatment-items/{$item->id}/complete", [
        'professional_id' => $this->professional->id,
        'encounter_id' => $encounter->id,
        'warehouse_id' => $this->warehouse->id,
    ])->assertSessionHasErrors('execution');

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($item->fresh()->status)->toBe('pending')
        ->and(TreatmentExecution::where('treatment_plan_item_id', $item->id)->exists())->toBeFalse()
        ->and(StockMovement::where('type', 'procedure_consumption')->count())->toBe(0);

    // A second rule for the same item proves the preflight aggregates requirements before any FIFO deduction.
    ProcedureMaterialRule::create([
        'procedure_id' => $this->procResina->id,
        'inventory_item_id' => $this->itemResina->id,
        'quantity_required' => 0.80,
    ]);
    app(InventoryStockService::class)->recordPurchase($this->itemResina, $this->warehouse, 'LOT-EXEC-01', 1, 25, null, $this->user->id);

    $this->post("http://inventario.bsdental.test/treatment-items/{$item->id}/complete", [
        'professional_id' => $this->professional->id,
        'encounter_id' => $encounter->id,
        'warehouse_id' => $this->warehouse->id,
    ])->assertSessionHasErrors('execution');

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($this->itemResina->fresh()->totalStock())->toBe(1.0)
        ->and(StockMovement::where('type', 'procedure_consumption')->count())->toBe(0);

    app(InventoryStockService::class)->recordPurchase($this->itemResina, $this->warehouse, 'LOT-EXEC-02', 0.10, 25, null, $this->user->id);

    $this->post("http://inventario.bsdental.test/treatment-items/{$item->id}/complete", [
        'professional_id' => $this->professional->id,
        'encounter_id' => $encounter->id,
        'warehouse_id' => $this->warehouse->id,
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $execution = TreatmentExecution::where('treatment_plan_item_id', $item->id)->firstOrFail();
    expect($item->fresh()->status)->toBe('completed')
        ->and($item->fresh()->encounter_id)->toBe($encounter->id)
        ->and(StockMovement::where('reference_type', 'TreatmentExecution')->where('reference_id', $execution->id)->count())->toBe(3)
        ->and(FollowUpTask::where('treatment_execution_id', $execution->id)->where('type', 'post_op')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'treatment_item.completed')->where('resource_id', $item->id)->exists())->toBeTrue();
});

test('[TREATMENT LAB] Lab orders can link only to eligible treatment items and follow the controlled lifecycle', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $plan = TreatmentPlan::create([
        'patient_id' => $this->patient->id,
        'title' => 'Plan protésico',
        'status' => 'active',
        'total_estimated' => 230.00,
    ]);
    $restoration = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'procedure_id' => $this->procResina->id, 'price' => 50, 'status' => 'pending']);
    $crown = TreatmentPlanItem::create(['treatment_plan_id' => $plan->id, 'procedure_id' => $this->procCorona->id, 'tooth_number' => 16, 'price' => 180, 'status' => 'pending']);

    $this->post('http://inventario.bsdental.test/lab/orders', [
        'patient_id' => $this->patient->id,
        'laboratory_id' => $this->lab->id,
        'treatment_plan_item_id' => $restoration->id,
        'work_description' => 'Orden no permitida',
    ])->assertSessionHasErrors('treatment_plan_item_id');

    $this->post('http://inventario.bsdental.test/lab/orders', [
        'patient_id' => $this->patient->id,
        'laboratory_id' => $this->lab->id,
        'treatment_plan_item_id' => $crown->id,
        'tooth_number' => 16,
        'work_description' => 'Corona de porcelana',
    ])->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    $order = LabOrder::where('treatment_plan_item_id', $crown->id)->firstOrFail();
    $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", ['status' => 'ready'])
        ->assertSessionHasErrors('status');

    foreach (['ordered', 'sent', 'in_progress', 'ready', 'received', 'delivered'] as $status) {
        $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", ['status' => $status])->assertRedirect();
    }

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($order->fresh()->status)->toBe('delivered')
        ->and(TenantAuditLog::where('action', 'lab_order.status_updated')->where('resource_id', $order->id)->count())->toBe(6);
});

test('[LAB-01 & LAB-02] Quality check reception and reject-and-remake order with parent linkage', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $labService = app(DentalLabService::class);

    $originalOrder = $labService->createOrder(
        $this->patient,
        $this->lab,
        'Corona Zirconio Monolítico Pieza 24',
        24,
        'A3',
        60.00,
        now()->addDays(5)->format('Y-m-d'),
        null,
        (string) $this->user->id
    );

    foreach (['ordered', 'sent', 'in_progress', 'ready'] as $status) {
        $labService->updateStatus($originalOrder, $status);
        $originalOrder->refresh();
    }

    // 1. Receive with quality check
    $qualityResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$originalOrder->id}/quality", [
        'final_cost' => 65.00,
        'quality_check_notes' => 'Ajuste oclusal verificado conforme en articulador',
    ]);
    $qualityResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $originalOrder->refresh();
    expect($originalOrder->status)->toBe('received')
        ->and($originalOrder->final_cost)->toBe(65.0)
        ->and($originalOrder->quality_check_notes)->toBe('Ajuste oclusal verificado conforme en articulador');

    // 2. Reject and request remake
    $remakeResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$originalOrder->id}/remake", [
        'remake_reason' => 'Discrepancia marginal detectada en prueba de estructura intraoral',
        'shade_guide' => 'A3.5',
        'estimated_cost' => 0.00,
    ]);
    $remakeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $originalOrder->refresh();
    expect($originalOrder->status)->toBe('rejected_remake')
        ->and($originalOrder->remake_reason)->toBe('Discrepancia marginal detectada en prueba de estructura intraoral');

    $remakeOrder = LabOrder::where('parent_order_id', $originalOrder->id)->firstOrFail();
    expect($remakeOrder->order_number)->toBe('LAB-00002')
        ->and($remakeOrder->status)->toBe('ordered')
        ->and($remakeOrder->shade_guide)->toBe('A3.5')
        ->and($remakeOrder->work_description)->toContain('RE-TRABAJO');
});
