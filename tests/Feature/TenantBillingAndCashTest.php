<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\Professional;
use App\Core\Models\Refund;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\BillingPaymentService;
use App\Core\Services\ProfessionalCompensationService;
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
        ->and($session->expected_cash)->toBe(170.0); // 100 opening + 70 cash split

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
