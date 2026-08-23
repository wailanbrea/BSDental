<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\DentalLaboratory;
use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\ProcedureMaterialRule;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\UserNotification;
use App\Core\Models\Warehouse;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\InventoryStockService;
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

    $this->dbPathInv = database_path('tenant_gate_inv_test.sqlite');
    if (! file_exists($this->dbPathInv)) {
        touch($this->dbPathInv);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Inventario y Lab Test',
        'slug' => 'inventario-test',
        'database_name' => $this->dbPathInv,
        'status' => 'active',
    ]);

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
    $movements = $stockService->consumeMaterialsForProcedure($planItem, $this->warehouse, (string) $this->user->id);

    expect(count($movements))->toBe(1)
        ->and($movements[0]->type)->toBe('procedure_consumption')
        ->and($movements[0]->quantity)->toBe(-0.25);

    $this->itemResina->refresh();
    expect($this->itemResina->totalStock())->toBe(14.75);

    // 3. Idempotent consumption: consuming again for same plan item must not duplicate deduction
    $duplicateMovements = $stockService->consumeMaterialsForProcedure($planItem, $this->warehouse, (string) $this->user->id);
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
    $readyResponse = $this->post("http://inventario.bsdental.test/lab/orders/{$order->id}/status", [
        'status' => 'ready',
    ]);
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
