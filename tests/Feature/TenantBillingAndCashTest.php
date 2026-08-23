<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\CashMovement;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\Professional;
use App\Core\Models\Refund;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\BillingPaymentService;
use App\Core\Services\CashRegisterService;
use App\Core\Services\ProfessionalCompensationService;
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

    $this->dbPathFin = database_path('tenant_gate_fin_test.sqlite');
    if (! file_exists($this->dbPathFin)) {
        touch($this->dbPathFin);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Finanzas Test',
        'slug' => 'finanzas-test',
        'database_name' => $this->dbPathFin,
        'status' => 'active',
    ]);

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

    // 6. Invariant 33 & 35: Refund $20 of cash from payment
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
        ->and($session->expected_cash)->toBe(150.0); // 170 - 20

    $receiptResponse = $this->get("http://finanzas.bsdental.test/payments/{$payment->id}");
    $receiptResponse->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Billing/Receipt')
            ->where('payment.payment_number', 'REC-00001')
            ->where('payment.patient.record_number', 'HC-00001')
            ->where('payment.total_amount', 120)
            ->where('payment.allocated_amount', 120)
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
