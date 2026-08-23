<?php

namespace App\Core\Controllers;

use App\Core\Models\CashSession;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Services\BillingPaymentService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        protected BillingPaymentService $billingService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display patient's account statement & billing dashboard.
     */
    public function index(string $patientId): Response
    {
        $patient = Patient::findOrFail($patientId);
        $charges = PatientCharge::with(['professional', 'treatmentPlanItem.procedure', 'allocations.payment'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::with(['splits', 'allocations.charge', 'refunds'])
            ->where('patient_id', $patientId)
            ->orderBy('paid_at', 'desc')
            ->get();

        $totalCharged = (float) $charges->sum('total_amount');
        $totalPaid = (float) $charges->sum('paid_amount');
        $balanceDue = (float) $charges->sum('balance_due');

        $activeCashSession = CashSession::where('status', 'open')->first();

        return Inertia::render('Clinic/Billing/Index', [
            'patient' => $patient,
            'charges' => $charges,
            'payments' => $payments,
            'totalCharged' => $totalCharged,
            'totalPaid' => $totalPaid,
            'balanceDue' => $balanceDue,
            'activeCashSession' => $activeCashSession,
        ]);
    }

    /**
     * Store new charge for a patient.
     */
    public function storeCharge(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate([
            'concept' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'professional_id' => ['nullable', 'uuid', 'exists:tenant.professionals,id'],
            'treatment_plan_item_id' => ['nullable', 'uuid', 'exists:tenant.treatment_plan_items,id'],
        ]);

        $userId = Auth::guard('web')->id();

        $charge = $this->billingService->createCharge(
            $patient,
            $validated['concept'],
            $validated['amount'],
            $validated['tax_amount'] ?? 0.00,
            $validated['treatment_plan_item_id'] ?? null,
            $validated['professional_id'] ?? null,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('billing.charge_created', 'PatientCharge', $charge->id, [
            'patient_id' => $patient->id,
            'charge_number' => $charge->charge_number,
            'total_amount' => $charge->total_amount,
        ]);

        return redirect()->back()->with('success', "Cargo {$charge->charge_number} generado por \${$charge->total_amount}.");
    }

    /**
     * Store payment with multi-splits.
     */
    public function storePayment(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate([
            'cash_session_id' => ['nullable', 'uuid', 'exists:tenant.cash_sessions,id'],
            'splits' => ['required', 'array', 'min:1'],
            'splits.*.method' => ['required', 'string', 'in:cash,credit_card,debit_card,transfer,zelle,insurance'],
            'splits.*.amount' => ['required', 'numeric', 'min:0.01'],
            'splits.*.reference_code' => ['nullable', 'string', 'max:100'],
            'auto_allocate_charge_id' => ['nullable', 'uuid', 'exists:tenant.patient_charges,id'],
        ]);

        $cashSession = ! empty($validated['cash_session_id']) ? CashSession::find($validated['cash_session_id']) : null;
        $userId = Auth::guard('web')->id();

        $payment = $this->billingService->recordPayment(
            $patient,
            $validated['splits'],
            $cashSession,
            $userId ? (string) $userId : null
        );

        // Auto allocate if requested
        if (! empty($validated['auto_allocate_charge_id'])) {
            $charge = PatientCharge::findOrFail($validated['auto_allocate_charge_id']);
            $allocAmount = min($payment->unallocated_amount, $charge->balance_due);
            if ($allocAmount > 0) {
                $this->billingService->allocatePayment($payment, $charge, $allocAmount);
            }
        }

        $this->auditLogger->logTenant('billing.payment_recorded', 'Payment', $payment->id, [
            'patient_id' => $patient->id,
            'payment_number' => $payment->payment_number,
            'total_amount' => $payment->total_amount,
        ]);

        return redirect()->back()->with('success', "Pago {$payment->payment_number} registrado por \${$payment->total_amount}.");
    }

    /**
     * Allocate payment.
     */
    public function allocate(Request $request, string $paymentId): RedirectResponse
    {
        $payment = Payment::findOrFail($paymentId);

        $validated = $request->validate([
            'patient_charge_id' => ['required', 'uuid', 'exists:tenant.patient_charges,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $charge = PatientCharge::findOrFail($validated['patient_charge_id']);

        $allocation = $this->billingService->allocatePayment(
            $payment,
            $charge,
            $validated['amount']
        );

        $this->auditLogger->logTenant('billing.payment_allocated', 'PaymentAllocation', $allocation->id, [
            'payment_number' => $payment->payment_number,
            'charge_number' => $charge->charge_number,
            'amount' => $allocation->amount,
        ]);

        return redirect()->back()->with('success', "Asignados \${$allocation->amount} al cargo {$charge->charge_number}.");
    }

    /**
     * Refund payment.
     */
    public function refund(Request $request, string $paymentId): RedirectResponse
    {
        $payment = Payment::findOrFail($paymentId);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:255'],
            'cash_session_id' => ['nullable', 'uuid', 'exists:tenant.cash_sessions,id'],
        ]);

        $cashSession = ! empty($validated['cash_session_id']) ? CashSession::find($validated['cash_session_id']) : null;
        $userId = Auth::guard('web')->id();

        $refund = $this->billingService->refundPayment(
            $payment,
            $validated['amount'],
            $validated['reason'],
            $cashSession,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('billing.payment_refunded', 'Refund', $refund->id, [
            'payment_number' => $payment->payment_number,
            'amount' => $refund->amount,
            'reason' => $refund->reason,
        ]);

        return redirect()->back()->with('success', "Reembolso procesado por \${$refund->amount}.");
    }
}
