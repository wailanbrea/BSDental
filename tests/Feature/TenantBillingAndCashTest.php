<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\CashMovement;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Models\CreditAdjustment;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\PaymentAllocation;
use App\Core\Models\Professional;
use App\Core\Models\Refund;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\BillingPaymentService;
use App\Core\Services\CashRegisterService;
use App\Core\Services\ProfessionalCompensationService;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
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

    $this->dbPathFin = $this->tenantDatabasePath('tenant_gate_fin_test.sqlite');
    if (! file_exists($this->dbPathFin)) {
        touch($this->dbPathFin);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Finanzas Test',
        'slug' => 'finanzas-test',
        'database_name' => $this->dbPathFin,
        'status' => 'active',
    ]);
    grantTenantModules($this->tenant, ['billing', 'finance']);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'finanzas.bsdental.test',
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
            'name' => 'Cajera María Rodríguez',
            'email' => 'maria@finanzas.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);

        $this->branch = Branch::create([
            'name' => 'Sede Financiera Principal',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->register = CashRegister::create([
            'branch_id' => $this->branch->id,
            'name' => 'Caja Principal 01',
            'is_active' => true,
        ]);

        $this->professional = Professional::create([
            'first_name' => 'Dr. Alejandro',
            'last_name' => 'Cirujano',
            'license_number' => 'COL-9988',
            'specialty' => 'Cirugía Bucal',
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Roberto',
            'last_name' => 'Pacheco',
            'phone' => '+58 412 111-2233',
            'status' => 'active',
        ]);
    });
});

test('[GATE-FIN] Comprehensive billing, multi-split payments, payment allocation, cash session blind count and commission accruals', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Open Cash Session with $100 opening balance
    $openResponse = $this->post("http://finanzas.bsdental.test/cash-registers/{$this->register->id}/open", [
        'opening_balance' => 100.00,
    ]);
    $openResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $session = CashSession::where('cash_register_id', $this->register->id)->where('status', 'open')->firstOrFail();
    expect($session->opening_balance)->toBe(100.0)
        ->and($session->expected_cash)->toBe(100.0);

    // 2. Invariant 30: Create Patient Charge (Receivable)
    $chargeResponse = $this->post("http://finanzas.bsdental.test/patients/{$this->patient->id}/billing/charges", [
        'concept' => 'Exodoncia Quirúrgica Tercer Molar',
        'amount' => 120.00,
        'tax_amount' => 0.00,
        'professional_id' => $this->professional->id,
    ]);
    $chargeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $charge = PatientCharge::where('patient_id', $this->patient->id)->firstOrFail();
    expect($charge->charge_number)->toBe('CHG-00001')
        ->and($charge->total_amount)->toBe(120.0)
        ->and($charge->balance_due)->toBe(120.0)
        ->and($charge->status)->toBe('pending');

    // 3. Invariant 31: Multi-split Payment ($70 cash + $50 credit_card = $120 total)
    $payResponse = $this->post("http://finanzas.bsdental.test/patients/{$this->patient->id}/billing/payments", [
        'cash_session_id' => $session->id,
        'splits' => [
            ['method' => 'cash', 'amount' => 70.00],
            ['method' => 'credit_card', 'amount' => 50.00, 'reference_code' => 'AUTH-4567'],
        ],
        'auto_allocate_charge_id' => $charge->id,
    ]);
    $payResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $payment = Payment::where('patient_id', $this->patient->id)->firstOrFail();
    expect($payment->total_amount)->toBe(120.0)
        ->and($payment->splits()->count())->toBe(2)
        ->and((float) $payment->splits()->sum('amount'))->toBe(120.0);

    // 4. Invariant 32: Allocation check ($120 allocated to charge -> charge is paid)
    $charge->refresh();
    $session->refresh();
    expect($charge->paid_amount)->toBe(120.0)
        ->and($charge->balance_due)->toBe(0.0)
        ->and($charge->status)->toBe('paid')
        ->and($session->expected_cash)->toBe(170.0) // 100 opening + 70 cash split
        ->and(CashMovement::where('type', 'patient_payment')->count())->toBe(2)
        ->and((float) CashMovement::where('payment_method', 'cash')->value('amount'))->toBe(70.0)
        ->and((float) CashMovement::where('payment_method', 'credit_card')->value('amount'))->toBe(50.0);

    // 5. Invariant 47: Accrue Professional Commission (30% on $120 = $36)
    $compService = app(ProfessionalCompensationService::class);
    $comp = $compService->accrueCommission($this->professional, $charge, 30.00, 'percentage_production');
    expect($comp->commission_amount)->toBe(36.0)
        ->and($comp->rate)->toBe(30.0)
        ->and($comp->status)->toBe('accrued');

    // 6. Invariant 33 & 35: Refund $20 reverses the latest allocation before cash leaves the clinic
    $refundResponse = $this->post("http://finanzas.bsdental.test/payments/{$payment->id}/refund", [
        'amount' => 20.00,
        'reason' => 'Ajuste de cortesía clínica',
        'cash_session_id' => $session->id,
    ]);
    $refundResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $payment->refresh();
    $session->refresh();
    expect($payment->refunded_amount)->toBe(20.0)
        ->and($payment->getRefundableBalance())->toBe(100.0)
        ->and($payment->allocated_amount)->toBe(100.0)
        ->and($payment->unallocated_amount)->toBe(0.0)
        ->and($charge->fresh()->paid_amount)->toBe(100.0)
        ->and($charge->fresh()->balance_due)->toBe(20.0)
        ->and($session->expected_cash)->toBe(150.0); // 170 - 20

    $receiptResponse = $this->get("http://finanzas.bsdental.test/payments/{$payment->id}");
    $receiptResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Billing/Receipt')
            ->where('payment.payment_number', 'REC-00001')
            ->where('payment.patient.record_number', 'HC-00001')
            ->where('payment.total_amount', 120)
            ->where('payment.allocated_amount', 100)
            ->where('payment.refunded_amount', 20)
            ->where('payment.net_amount', 100)
            ->has('payment.splits', 2)
            ->where('payment.splits.1.reference_code', 'AUTH-4567')
            ->has('payment.allocations', 1)
            ->where('payment.allocations.0.charge.charge_number', 'CHG-00001')
            ->has('payment.refunds', 1)
            ->where('payment.refunds.0.reason', 'Ajuste de cortesía clínica'));

    // 7. Invariant 36 & 38 & 39: Close Cash Session with Blind Count ($150 counted -> 0 difference)
    $closeResponse = $this->post("http://finanzas.bsdental.test/cash-sessions/{$session->id}/close", [
        'counted_cash' => 150.00,
        'closing_notes' => 'Arqueo perfecto de cierre de turno',
    ]);
    $closeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $session->refresh();
    expect($session->status)->toBe('closed')
        ->and($session->counted_cash)->toBe(150.0)
        ->and($session->difference)->toBe(0.0);

    // 8. Invariant 36: Cannot record payments on a closed session
    $billingService = app(BillingPaymentService::class);
    expect(fn () => $billingService->recordPayment(
        $this->patient,
        [['method' => 'cash', 'amount' => 10.00]],
        $session,
        (string) $this->user->id
    ))->toThrow(InvalidArgumentException::class);

    // 9. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'cash.session_opened')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'billing.charge_created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'billing.payment_recorded')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'billing.payment_refunded')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'cash.session_closed')->exists())->toBeTrue();
});

test('[GATE-FIN] Manual allocation is patient-scoped, charge detail is reconciled and closed sessions reject refunds', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $billingService = app(BillingPaymentService::class);
    $charge = $billingService->createCharge($this->patient, 'Corona de zirconio', 80.00);
    $payment = $billingService->recordPayment(
        $this->patient,
        [['method' => 'transfer', 'amount' => 50.00, 'reference_code' => 'TRX-9001']],
        null,
        (string) $this->user->id
    );

    $otherPatient = Patient::create([
        'record_number' => 'HC-00002',
        'first_name' => 'Paciente',
        'last_name' => 'Distinto',
        'status' => 'active',
    ]);
    $otherCharge = $billingService->createCharge($otherPatient, 'Consulta externa', 20.00);

    $crossPatientResponse = $this->post("http://finanzas.bsdental.test/payments/{$payment->id}/allocate", [
        'patient_charge_id' => $otherCharge->id,
        'amount' => 10.00,
    ]);
    $crossPatientResponse->assertSessionHasErrors('patient_charge_id');
    $context->makeCurrent($this->tenant);
    expect($payment->fresh()->unallocated_amount)->toBe(50.0)
        ->and($otherCharge->fresh()->paid_amount)->toBe(0.0)
        ->and(fn () => $billingService->allocatePayment($payment, $otherCharge, 10.00))
        ->toThrow(InvalidArgumentException::class, 'El pago y el cargo deben pertenecer al mismo paciente.');

    $allocationResponse = $this->post("http://finanzas.bsdental.test/payments/{$payment->id}/allocate", [
        'patient_charge_id' => $charge->id,
        'amount' => 50.00,
        'reason' => 'Aplicación manual al cargo de corona',
        'idempotency_key' => 'allocation-detail-test-01',
    ]);
    $allocationResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $charge->refresh();
    expect($charge->paid_amount)->toBe(50.0)
        ->and($charge->balance_due)->toBe(30.0)
        ->and($charge->status)->toBe('partially_paid');

    $detailResponse = $this->get("http://finanzas.bsdental.test/charges/{$charge->id}");
    $detailResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Billing/Charge')
            ->where('charge.charge_number', $charge->charge_number)
            ->where('charge.patient.record_number', 'HC-00001')
            ->where('charge.paid_amount', 50)
            ->where('charge.balance_due', 30)
            ->has('charge.allocations', 1)
            ->where('charge.allocations.0.payment.payment_number', $payment->payment_number)
            ->where('charge.allocations.0.payment.methods.0', 'transfer'));

    $context->makeCurrent($this->tenant);
    $closedSession = CashSession::create([
        'cash_register_id' => $this->register->id,
        'opened_by_user_id' => $this->user->id,
        'closed_by_user_id' => $this->user->id,
        'status' => 'closed',
        'opening_balance' => 0,
        'expected_cash' => 0,
        'counted_cash' => 0,
        'difference' => 0,
        'opened_at' => now()->subHour(),
        'closed_at' => now(),
    ]);

    expect(fn () => $billingService->refundPayment(
        $payment,
        10.00,
        'Intento sobre caja cerrada',
        $closedSession,
        (string) $this->user->id
    ))->toThrow(InvalidArgumentException::class, 'La sesión de caja seleccionada no está abierta.');
});

test('[GATE-FIN] Cash payments require an open session and manual movements only reconcile physical cash', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $paymentWithoutCashSession = $this->post("http://finanzas.bsdental.test/patients/{$this->patient->id}/billing/payments", [
        'splits' => [
            ['method' => 'cash', 'amount' => 15.00],
        ],
    ]);
    $paymentWithoutCashSession->assertSessionHasErrors('cash_session_id');

    $context->makeCurrent($this->tenant);
    expect(Payment::count())->toBe(0)
        ->and(fn () => app(BillingPaymentService::class)->recordPayment(
            $this->patient,
            [['method' => 'cash', 'amount' => 15.00]],
            null,
            (string) $this->user->id
        ))->toThrow(InvalidArgumentException::class, 'Los pagos en efectivo requieren una sesión de caja abierta.');

    $cashService = app(CashRegisterService::class);
    $session = $cashService->openSession($this->register, $this->user, 100.00);

    $cashIncomeResponse = $this->post("http://finanzas.bsdental.test/cash-sessions/{$session->id}/movements", [
        'type' => 'manual_income',
        'amount' => 25.00,
        'payment_method' => 'cash',
        'concept' => 'Fondo adicional autorizado',
    ]);
    $cashIncomeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $session->refresh();
    expect($session->expected_cash)->toBe(125.0)
        ->and((float) CashMovement::where('type', 'manual_income')->value('amount'))->toBe(25.0)
        ->and(TenantAuditLog::where('action', 'cash.manual_movement_recorded')->exists())->toBeTrue();

    $transferExpenseResponse = $this->post("http://finanzas.bsdental.test/cash-sessions/{$session->id}/movements", [
        'type' => 'manual_expense',
        'amount' => 10.00,
        'payment_method' => 'transfer',
        'concept' => 'Servicio pagado por transferencia',
    ]);
    $transferExpenseResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $session->refresh();
    expect($session->expected_cash)->toBe(125.0)
        ->and((float) CashMovement::where('type', 'manual_expense')->value('amount'))->toBe(-10.0);

    $cashService->closeSession($session, $this->user, 125.00, 'Cierre conciliado');
    expect(fn () => $cashService->recordManualMovement(
        $session,
        $this->user,
        'manual_income',
        5.00,
        'Intento tardío',
        'cash'
    ))->toThrow(InvalidArgumentException::class, 'La sesión de caja seleccionada no está abierta.');
});

test('[FIN-01 & FIN-02] Controlled cash session reopening with audit and multi-register simultaneous sessions', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $cashService = app(CashRegisterService::class);

    // Create a second branch and second cash register
    $branch2 = Branch::create([
        'name' => 'Sede Norte',
        'is_main' => false,
        'status' => 'active',
    ]);
    $register2 = CashRegister::create([
        'branch_id' => $branch2->id,
        'name' => 'Caja Norte 01',
        'is_active' => true,
    ]);

    // Open sessions in both registers simultaneously (FIN-02)
    $session1 = $cashService->openSession($this->register, $this->user, 100.00);
    $session2 = $cashService->openSession($register2, $this->user, 200.00);

    expect($session1->status)->toBe('open')
        ->and($session2->status)->toBe('open')
        ->and($session1->cash_register_id)->toBe($this->register->id)
        ->and($session2->cash_register_id)->toBe($register2->id);

    // Test index page lists multiple active sessions and registers
    $indexResponse = $this->get('http://finanzas.bsdental.test/cash-registers');
    $indexResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Cash/Index')
            ->has('registers', 2)
            ->has('activeSessions', 2)
            ->where('canReopen', true)
        );

    // Close session 1
    $context->makeCurrent($this->tenant);
    $cashService->closeSession($session1, $this->user, 100.00, 'Cierre de turno tarde');
    $session1->refresh();
    expect($session1->status)->toBe('closed')
        ->and($session1->closed_at)->not->toBeNull();

    // Reopen validation: reason too short (< 10 chars) -> 422
    $shortReasonResponse = $this->post("http://finanzas.bsdental.test/cash-sessions/{$session1->id}/reopen", [
        'reason' => 'Error',
    ]);
    $shortReasonResponse->assertSessionHasErrors(['reason']);

    // Valid reopening (FIN-01)
    $validReopenResponse = $this->post("http://finanzas.bsdental.test/cash-sessions/{$session1->id}/reopen", [
        'reason' => 'Ajuste de cobro no registrado por corte de energía eléctrica',
    ]);
    $validReopenResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $session1->refresh();
    expect($session1->status)->toBe('open')
        ->and($session1->closed_at)->toBeNull()
        ->and($session1->closing_notes)->toContain('REAPERTURA')
        ->and(TenantAuditLog::where('action', 'cash.session_reopened')->exists())->toBeTrue();

    // Prevent reopening when another session on the same register is already open
    $session1Again = $cashService->closeSession($session1, $this->user, 100.00, 'Segundo cierre');
    $newSessionOnReg1 = $cashService->openSession($this->register, $this->user, 50.00);

    expect(fn () => $cashService->reopenSession($session1Again, $this->user, 'Intento de doble sesión abierta'))
        ->toThrow(InvalidArgumentException::class, 'Esta caja ya cuenta con una sesión abierta actualmente.');
});

test('[FIN-03] Detailed cash session audit view, payment method breakdown and CSV export', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $cashService = app(CashRegisterService::class);
    $billingService = app(BillingPaymentService::class);

    $session = $cashService->openSession($this->register, $this->user, 150.00);

    // Add multiple payments and manual movements
    $cashService->recordManualMovement($session, $this->user, 'manual_income', 50.00, 'Fondo de cambio extra', 'cash');
    $cashService->recordManualMovement($session, $this->user, 'manual_expense', 20.00, 'Material de limpieza', 'cash');
    $billingService->recordPayment($this->patient, [
        ['method' => 'cash', 'amount' => 80.00],
        ['method' => 'zelle', 'amount' => 40.00, 'reference_code' => 'ZEL-9988'],
        ['method' => 'credit_card', 'amount' => 60.00, 'reference_code' => 'CARD-1122'],
    ], $session, (string) $this->user->id);

    // Test show session endpoint
    $showResponse = $this->get("http://finanzas.bsdental.test/cash-sessions/{$session->id}");
    $showResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Cash/Show')
            ->where('session.id', $session->id)
            ->where('methodTotals.cash', 110)
            ->where('methodTotals.zelle', 40)
            ->where('methodTotals.credit_card', 60)
            ->where('totalIncome', 230)
            ->where('totalExpense', 20)
        );

    // Test CSV export endpoint
    $exportResponse = $this->get("http://finanzas.bsdental.test/cash-sessions/{$session->id}/export");
    $exportResponse->assertOk();
    $exportResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $context->makeCurrent($this->tenant);
    expect(TenantAuditLog::where('action', 'cash.session_exported')->exists())->toBeTrue();
});

test('[FIN-04] Financial idempotency on payments, refunds and manual cash movements', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $cashService = app(CashRegisterService::class);
    $billingService = app(BillingPaymentService::class);

    $session = $cashService->openSession($this->register, $this->user, 100.00);

    // 1. Payment idempotency
    $idemKey1 = 'pay-idem-key-001';
    $payment1 = $billingService->recordPayment(
        $this->patient,
        [['method' => 'cash', 'amount' => 50.00]],
        $session,
        (string) $this->user->id,
        $idemKey1
    );

    expect(Payment::count())->toBe(1)
        ->and($payment1->idempotency_key)->toBe($idemKey1);

    // Replay with identical key & payload returns existing payment without creating a second record
    $payment1Replay = $billingService->recordPayment(
        $this->patient,
        [['method' => 'cash', 'amount' => 50.00]],
        $session,
        (string) $this->user->id,
        $idemKey1
    );

    expect(Payment::count())->toBe(1)
        ->and($payment1Replay->id)->toBe($payment1->id);

    // Same key with different payload throws InvalidArgumentException
    expect(fn () => $billingService->recordPayment(
        $this->patient,
        [['method' => 'cash', 'amount' => 99.00]],
        $session,
        (string) $this->user->id,
        $idemKey1
    ))->toThrow(InvalidArgumentException::class, 'Clave de idempotencia ya utilizada con parámetros diferentes.');

    // 2. Refund idempotency
    $idemKeyRefund = 'ref-idem-key-001';
    $refund1 = $billingService->refundPayment($payment1, 20.00, 'Devolución parcial', $session, (string) $this->user->id, $idemKeyRefund);
    expect(Refund::count())->toBe(1);

    // Replay with same key returns existing refund
    $refund1Replay = $billingService->refundPayment($payment1, 20.00, 'Devolución parcial', $session, (string) $this->user->id, $idemKeyRefund);
    expect(Refund::count())->toBe(1)
        ->and($refund1Replay->id)->toBe($refund1->id);

    // 3. Manual movement idempotency
    $idemKeyMove = 'mov-idem-key-001';
    $move1 = $cashService->recordManualMovement($session, $this->user, 'manual_income', 30.00, 'Fondo adicional', 'cash', $idemKeyMove);
    $move1Replay = $cashService->recordManualMovement($session, $this->user, 'manual_income', 30.00, 'Fondo adicional', 'cash', $idemKeyMove);

    expect($move1Replay->id)->toBe($move1->id)
        ->and(CashMovement::where('idempotency_key', $idemKeyMove)->count())->toBe(1);
});

test('[FIN-05] Credit adjustments and credit notes update charge balance and produce audit trail', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $billingService = app(BillingPaymentService::class);

    $charge = $billingService->createCharge(
        $this->patient,
        'Corona Porcelana Premium',
        300.00,
        0.00,
        null,
        $this->professional->id,
        (string) $this->user->id
    );

    expect($charge->total_amount)->toBe(300.0)
        ->and($charge->balance_due)->toBe(300.0)
        ->and($charge->adjusted_amount)->toBe(0.0);

    // 1. Partial credit adjustment
    $adjustmentResponse = $this->post("http://finanzas.bsdental.test/charges/{$charge->id}/adjustments", [
        'amount' => 50.00,
        'type' => 'subsequent_discount',
        'reason' => 'Descuento especial por convenio corporativo',
    ]);
    $adjustmentResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $charge->refresh();
    expect($charge->adjusted_amount)->toBe(50.0)
        ->and($charge->balance_due)->toBe(250.0)
        ->and($charge->status)->toBe('pending')
        ->and(CreditAdjustment::count())->toBe(1)
        ->and(TenantAuditLog::where('action', 'billing.credit_adjustment_created')->exists())->toBeTrue();

    $creditNote = CreditAdjustment::first();
    expect($creditNote->credit_note_number)->toBe('NC-00001')
        ->and($creditNote->type)->toBe('subsequent_discount')
        ->and($creditNote->amount)->toBe(50.0);

    // 2. Full settlement with second adjustment
    $fullAdjustmentResponse = $this->post("http://finanzas.bsdental.test/charges/{$charge->id}/adjustments", [
        'amount' => 250.00,
        'type' => 'correction',
        'reason' => 'Ajuste final por garantía de servicio',
    ]);
    $fullAdjustmentResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $charge->refresh();
    expect($charge->adjusted_amount)->toBe(300.0)
        ->and($charge->balance_due)->toBe(0.0)
        ->and($charge->status)->toBe('paid');

    // 3. Excess adjustment should fail validation
    $excessResponse = $this->post("http://finanzas.bsdental.test/charges/{$charge->id}/adjustments", [
        'amount' => 10.00,
        'type' => 'correction',
        'reason' => 'Exceso no permitido',
    ]);
    $excessResponse->assertSessionHasErrors(['amount']);
});

test('[FIN-06] Comprehensive patient account statement and aging receivables buckets report', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);
    $this->actingAs($this->user, 'web');

    $billingService = app(BillingPaymentService::class);

    // Create charges of different ages
    $chargeCurrent = $billingService->createCharge($this->patient, 'Limpieza Dental', 100.00);
    $charge35Days = $billingService->createCharge($this->patient, 'Endodoncia Molar', 200.00);
    $charge35Days->created_at = Carbon::now()->subDays(35);
    $charge35Days->save();

    $charge100Days = $billingService->createCharge($this->patient, 'Implante de Titanio', 500.00);
    $charge100Days->created_at = Carbon::now()->subDays(100);
    $charge100Days->save();

    // 1. Test Patient Statement endpoint
    $statementResponse = $this->get("http://finanzas.bsdental.test/patients/{$this->patient->id}/billing/statement");
    $statementResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Billing/Statement')
            ->where('statement.patient.id', $this->patient->id)
            ->where('statement.summary.total_charged', 800)
            ->where('statement.summary.net_balance_due', 800)
        );

    // 2. Test Aging Receivables report endpoint
    $agingResponse = $this->get('http://finanzas.bsdental.test/billing/aging-receivables');
    $agingResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Billing/AgingReceivables')
            ->where('report.total_receivable', 800)
            ->where('report.total_charges_count', 3)
            ->where('report.buckets.current_30.total', 100)
            ->where('report.buckets.aging_31_60.total', 200)
            ->where('report.buckets.over_90.total', 500)
        );
});

test('[FIN-07] Locked allocations reject stale repeats and refunds reverse allocations without creating credit', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);

    $billingService = app(BillingPaymentService::class);
    $charge = $billingService->createCharge($this->patient, 'Restauración posterior', 100.00);
    $payment = $billingService->recordPayment($this->patient, [
        ['method' => 'transfer', 'amount' => 100.00, 'reference_code' => 'TRX-LOCK-01'],
    ]);
    $stalePayment = Payment::findOrFail($payment->id);
    $staleCharge = PatientCharge::findOrFail($charge->id);

    $billingService->allocatePayment($payment, $charge, 100.00);

    expect(fn () => $billingService->allocatePayment($stalePayment, $staleCharge, 1.00))
        ->toThrow(InvalidArgumentException::class, 'El monto excede el saldo no asignado disponible en este pago.');

    expect(PaymentAllocation::count())->toBe(1)
        ->and($payment->fresh()->unallocated_amount)->toBe(0.0)
        ->and($charge->fresh()->balance_due)->toBe(0.0);

    $refund = $billingService->refundPayment($stalePayment, 40.00, 'Corrección de cobro', null, null, 'refund-lock-01');
    $refundReplay = $billingService->refundPayment($stalePayment, 40.00, 'Corrección de cobro', null, null, 'refund-lock-01');

    expect($refundReplay->id)->toBe($refund->id)
        ->and(Refund::count())->toBe(1)
        ->and($payment->fresh()->allocated_amount)->toBe(60.0)
        ->and($payment->fresh()->unallocated_amount)->toBe(0.0)
        ->and($charge->fresh()->paid_amount)->toBe(60.0)
        ->and($charge->fresh()->balance_due)->toBe(40.0)
        ->and(PaymentAllocation::firstOrFail()->reversed_amount)->toBe(40.0);

    expect(fn () => $billingService->refundPayment($payment, 61.00, 'Exceso de reembolso'))
        ->toThrow(InvalidArgumentException::class, 'El monto del reembolso excede el saldo reembolsable disponible.');
});

test('[FIN-08] Customer credit is explicit in the statement and is not auto-applied to charges', function () {
    app(TenantContext::class)->makeCurrent($this->tenant);

    $billingService = app(BillingPaymentService::class);
    $charge = $billingService->createCharge($this->patient, 'Limpieza preventiva', 100.00);
    $payment = $billingService->recordPayment($this->patient, [
        ['method' => 'transfer', 'amount' => 100.00, 'reference_code' => 'TRX-CREDIT-01'],
    ]);

    $statement = $billingService->getPatientAccountStatement($this->patient);

    expect($charge->fresh()->paid_amount)->toBe(0.0)
        ->and($charge->fresh()->balance_due)->toBe(100.0)
        ->and($statement['summary']['net_balance_due'])->toBe(100.0)
        ->and($statement['summary']['customer_credit'])->toBe(100.0)
        ->and($statement['summary']['payer_balance'])->toBe(0.0)
        ->and($statement['summary']['saldo_a_favor'])->toBe(0.0);

    $futureCharge = $billingService->createCharge($this->patient, 'Control futuro', 60.00);
    $payload = [
        'patient_charge_id' => $futureCharge->id,
        'amount' => 60.00,
        'reason' => 'Autorización del paciente para aplicar saldo a favor',
        'idempotency_key' => 'credit-application-fin-08',
    ];

    $this->actingAs($this->user, 'web')
        ->post("http://finanzas.bsdental.test/payments/{$payment->id}/allocate", $payload)
        ->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($payment->fresh()->unallocated_amount)->toBe(40.0)
        ->and($futureCharge->fresh()->balance_due)->toBe(0.0)
        ->and($futureCharge->fresh()->status)->toBe('paid')
        ->and(PaymentAllocation::where('idempotency_key', 'credit-application-fin-08')->count())->toBe(1)
        ->and(PaymentAllocation::where('idempotency_key', 'credit-application-fin-08')->firstOrFail()->created_by_user_id)->toBe($this->user->id)
        ->and(TenantAuditLog::where('action', 'billing.payment_allocated')->count())->toBe(1);

    $this->post("http://finanzas.bsdental.test/payments/{$payment->id}/allocate", $payload)
        ->assertRedirect();

    app(TenantContext::class)->makeCurrent($this->tenant);
    expect($payment->fresh()->unallocated_amount)->toBe(40.0)
        ->and($futureCharge->fresh()->paid_amount)->toBe(60.0)
        ->and(PaymentAllocation::where('idempotency_key', 'credit-application-fin-08')->count())->toBe(1)
        ->and(TenantAuditLog::where('action', 'billing.payment_allocated')->count())->toBe(1);
});
