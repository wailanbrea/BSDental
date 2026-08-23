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
use Illuminate\Validation\Rule;
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
     * Display one charge and the payments allocated to it.
     */
    public function showCharge(string $chargeId): Response
    {
        $charge = PatientCharge::with([
            'patient',
            'professional',
            'treatmentPlanItem.procedure',
            'allocations.payment.splits',
            'allocations.payment.refunds',
            'createdBy',
        ])->findOrFail($chargeId);

        return Inertia::render('Clinic/Billing/Charge', [
            'charge' => [
                'id' => $charge->id,
                'charge_number' => $charge->charge_number,
                'concept' => $charge->concept,
                'amount' => $charge->amount,
                'tax_amount' => $charge->tax_amount,
                'total_amount' => $charge->total_amount,
                'paid_amount' => $charge->paid_amount,
                'balance_due' => $charge->balance_due,
                'status' => $charge->status,
                'due_date' => $charge->due_date,
                'created_at' => $charge->created_at,
                'created_by' => $charge->createdBy?->name,
                'professional' => $charge->professional?->full_name,
                'procedure' => $charge->treatmentPlanItem?->procedure?->name,
                'patient' => [
                    'id' => $charge->patient->id,
                    'record_number' => $charge->patient->record_number,
                    'full_name' => $charge->patient->full_name,
                ],
                'allocations' => $charge->allocations->map(fn ($allocation) => [
                    'id' => $allocation->id,
                    'amount' => $allocation->amount,
                    'allocated_at' => $allocation->allocated_at,
                    'payment' => [
                        'id' => $allocation->payment->id,
                        'payment_number' => $allocation->payment->payment_number,
                        'total_amount' => $allocation->payment->total_amount,
                        'refunded_amount' => $allocation->payment->refunded_amount,
                        'status' => $allocation->payment->status,
                        'paid_at' => $allocation->payment->paid_at,
                        'methods' => $allocation->payment->splits->pluck('method')->values(),
                    ],
                ])->values(),
            ],
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
            'cash_session_id' => ['nullable', 'uuid', Rule::exists('tenant.cash_sessions', 'id')->where('status', 'open')],
            'splits' => ['required', 'array', 'min:1'],
            'splits.*.method' => ['required', 'string', 'in:cash,credit_card,debit_card,transfer,zelle,insurance'],
            'splits.*.amount' => ['required', 'numeric', 'min:0.01'],
            'splits.*.reference_code' => ['nullable', 'string', 'max:100'],
            'auto_allocate_charge_id' => [
                'nullable',
                'uuid',
                Rule::exists('tenant.patient_charges', 'id')->where('patient_id', $patient->id),
            ],
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
     * Display a printable, auditable payment receipt.
     */
    public function showPayment(string $paymentId): Response
    {
        $payment = Payment::with([
            'patient',
            'splits',
            'allocations.charge',
            'refunds.createdBy',
            'cashSession.cashRegister',
            'createdBy',
        ])->findOrFail($paymentId);

        return Inertia::render('Clinic/Billing/Receipt', [
            'payment' => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'total_amount' => $payment->total_amount,
                'allocated_amount' => $payment->allocated_amount,
                'unallocated_amount' => $payment->unallocated_amount,
                'refunded_amount' => $payment->refunded_amount,
                'net_amount' => round($payment->total_amount - $payment->refunded_amount, 2),
                'status' => $payment->status,
                'paid_at' => $payment->paid_at,
                'created_at' => $payment->created_at,
                'created_by' => $payment->createdBy?->name,
                'cash_register' => $payment->cashSession?->cashRegister?->name,
                'patient' => [
                    'id' => $payment->patient->id,
                    'record_number' => $payment->patient->record_number,
                    'full_name' => $payment->patient->full_name,
                    'identification_number' => $payment->patient->identification_number,
                    'phone' => $payment->patient->phone,
                    'email' => $payment->patient->email,
                ],
                'splits' => $payment->splits->map(fn ($split) => [
                    'id' => $split->id,
                    'method' => $split->method,
                    'amount' => $split->amount,
                    'reference_code' => $split->reference_code,
                ])->values(),
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'id' => $allocation->id,
                    'amount' => $allocation->amount,
                    'allocated_at' => $allocation->allocated_at,
                    'charge' => [
                        'id' => $allocation->charge->id,
                        'charge_number' => $allocation->charge->charge_number,
                        'concept' => $allocation->charge->concept,
                        'total_amount' => $allocation->charge->total_amount,
                        'status' => $allocation->charge->status,
                    ],
                ])->values(),
                'refunds' => $payment->refunds->map(fn ($refund) => [
                    'id' => $refund->id,
                    'amount' => $refund->amount,
                    'reason' => $refund->reason,
                    'refunded_at' => $refund->refunded_at,
                    'created_by' => $refund->createdBy?->name,
                ])->values(),
            ],
        ]);
    }

    /**
     * Allocate payment.
     */
    public function allocate(Request $request, string $paymentId): RedirectResponse
    {
        $payment = Payment::findOrFail($paymentId);

        $validated = $request->validate([
            'patient_charge_id' => [
                'required',
                'uuid',
                Rule::exists('tenant.patient_charges', 'id')->where('patient_id', $payment->patient_id),
            ],
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
            'cash_session_id' => ['nullable', 'uuid', Rule::exists('tenant.cash_sessions', 'id')->where('status', 'open')],
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
