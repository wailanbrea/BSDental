<?php

namespace App\Core\Services;

use App\Core\Models\CashMovement;
use App\Core\Models\CashSession;
use App\Core\Models\CreditAdjustment;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\PaymentAllocation;
use App\Core\Models\PaymentSplit;
use App\Core\Models\Refund;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BillingPaymentService
{
    /**
     * Generate sequential charge number.
     */
    public function generateChargeNumber(): string
    {
        $count = PatientCharge::count() + 1;

        do {
            $formatted = sprintf('CHG-%05d', $count);
            $exists = PatientCharge::where('charge_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }

    /**
     * Generate sequential payment number.
     */
    public function generatePaymentNumber(): string
    {
        $count = Payment::count() + 1;

        do {
            $formatted = sprintf('REC-%05d', $count);
            $exists = Payment::where('payment_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }

    /**
     * Create patient charge (receivable).
     */
    public function createCharge(
        Patient $patient,
        string $concept,
        float $amount,
        float $taxAmount = 0.00,
        ?string $treatmentPlanItemId = null,
        ?string $professionalId = null,
        ?string $userId = null
    ): PatientCharge {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del cargo debe ser mayor a cero.');
        }

        $totalAmount = $amount + $taxAmount;

        return PatientCharge::create([
            'patient_id' => $patient->id,
            'treatment_plan_item_id' => $treatmentPlanItemId,
            'professional_id' => $professionalId,
            'charge_number' => $this->generateChargeNumber(),
            'concept' => $concept,
            'amount' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => 0.00,
            'adjusted_amount' => 0.00,
            'balance_due' => $totalAmount,
            'status' => 'pending',
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Record payment with multi-method splits and optional idempotency key.
     *
     * @param  list<array{method: string, amount: float, reference_code?: string|null}>  $splits
     */
    public function recordPayment(
        Patient $patient,
        array $splits,
        ?CashSession $cashSession = null,
        ?string $userId = null,
        ?string $idempotencyKey = null
    ): Payment {
        if (empty($splits)) {
            throw new InvalidArgumentException('El pago debe contener al menos un método de pago.');
        }

        if ($cashSession && $cashSession->status !== 'open') {
            throw new InvalidArgumentException('La sesión de caja seleccionada no está abierta.');
        }

        $totalAmount = 0.0;
        foreach ($splits as $sp) {
            if ($sp['amount'] <= 0) {
                throw new InvalidArgumentException('Cada split de pago debe ser mayor a cero.');
            }
            $totalAmount += $sp['amount'];
        }

        // Idempotency check
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Payment::with(['splits'])->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->patient_id !== $patient->id || (float) $existing->total_amount !== (float) $totalAmount) {
                    throw new InvalidArgumentException('Clave de idempotencia ya utilizada con parámetros diferentes.');
                }

                return $existing;
            }
        }

        return DB::connection('tenant')->transaction(function () use ($patient, $splits, $totalAmount, $cashSession, $userId, $idempotencyKey) {
            $paymentNumber = $this->generatePaymentNumber();

            $payment = Payment::create([
                'patient_id' => $patient->id,
                'cash_session_id' => $cashSession?->id,
                'payment_number' => $paymentNumber,
                'total_amount' => $totalAmount,
                'allocated_amount' => 0.00,
                'unallocated_amount' => $totalAmount,
                'refunded_amount' => 0.00,
                'status' => 'confirmed',
                'idempotency_key' => $idempotencyKey,
                'paid_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $cashSplitTotal = 0.0;

            foreach ($splits as $splitData) {
                PaymentSplit::create([
                    'payment_id' => $payment->id,
                    'method' => $splitData['method'],
                    'amount' => $splitData['amount'],
                    'reference_code' => $splitData['reference_code'] ?? null,
                ]);

                if ($splitData['method'] === 'cash') {
                    $cashSplitTotal += $splitData['amount'];
                }
            }

            if ($cashSplitTotal > 0 && ! $cashSession) {
                throw new InvalidArgumentException('Los pagos en efectivo requieren una sesión de caja abierta.');
            }

            if ($cashSession) {
                foreach ($splits as $splitData) {
                    CashMovement::create([
                        'cash_session_id' => $cashSession->id,
                        'type' => 'patient_payment',
                        'amount' => $splitData['amount'],
                        'payment_method' => $splitData['method'],
                        'concept' => "Cobro {$paymentNumber} - {$patient->full_name}",
                        'created_by_user_id' => $userId,
                        'created_at' => now(),
                    ]);
                }

                if ($cashSplitTotal > 0) {
                    $cashSession->update([
                        'expected_cash' => $cashSession->expected_cash + $cashSplitTotal,
                    ]);
                }
            }

            return $payment;
        });
    }

    /**
     * Allocate payment amount to a specific patient charge.
     */
    public function allocatePayment(
        Payment $payment,
        PatientCharge $charge,
        float $amount
    ): PaymentAllocation {
        if ($payment->patient_id !== $charge->patient_id) {
            throw new InvalidArgumentException('El pago y el cargo deben pertenecer al mismo paciente.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto a asignar debe ser mayor a cero.');
        }

        if ($amount > $payment->unallocated_amount) {
            throw new InvalidArgumentException('El monto excede el saldo no asignado disponible en este pago.');
        }

        if ($amount > $charge->balance_due) {
            throw new InvalidArgumentException('El monto excede el saldo pendiente del cargo seleccionado.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $charge, $amount) {
            $allocation = PaymentAllocation::create([
                'payment_id' => $payment->id,
                'patient_charge_id' => $charge->id,
                'amount' => $amount,
                'allocated_at' => now(),
            ]);

            $newAllocated = $payment->allocated_amount + $amount;
            $newUnallocated = $payment->unallocated_amount - $amount;
            $paymentStatus = $newUnallocated <= 0 ? 'fully_allocated' : 'partially_allocated';

            $payment->update([
                'allocated_amount' => $newAllocated,
                'unallocated_amount' => $newUnallocated,
                'status' => $paymentStatus,
            ]);

            $newPaid = $charge->paid_amount + $amount;
            $newBalance = $charge->balance_due - $amount;
            $chargeStatus = $newBalance <= 0 ? 'paid' : 'partially_paid';

            $charge->update([
                'paid_amount' => $newPaid,
                'balance_due' => $newBalance,
                'status' => $chargeStatus,
            ]);

            return $allocation;
        });
    }

    /**
     * Process a refund on a payment with optional idempotency key.
     */
    public function refundPayment(
        Payment $payment,
        float $amount,
        string $reason,
        ?CashSession $cashSession = null,
        ?string $userId = null,
        ?string $idempotencyKey = null
    ): Refund {
        if ($cashSession && $cashSession->status !== 'open') {
            throw new InvalidArgumentException('La sesión de caja seleccionada no está abierta.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del reembolso debe ser mayor a cero.');
        }

        if ($amount > $payment->getRefundableBalance()) {
            throw new InvalidArgumentException('El monto del reembolso excede el saldo reembolsable disponible.');
        }

        // Idempotency check for refund
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Refund::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->payment_id !== $payment->id || (float) $existing->amount !== (float) $amount) {
                    throw new InvalidArgumentException('Clave de idempotencia ya utilizada con parámetros diferentes.');
                }

                return $existing;
            }
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $amount, $reason, $cashSession, $userId, $idempotencyKey) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'patient_id' => $payment->patient_id,
                'cash_session_id' => $cashSession?->id,
                'amount' => $amount,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'refunded_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $payment->update([
                'refunded_amount' => $payment->refunded_amount + $amount,
                'status' => 'refunded',
            ]);

            if ($cashSession) {
                CashMovement::create([
                    'cash_session_id' => $cashSession->id,
                    'type' => 'patient_refund',
                    'amount' => -$amount,
                    'payment_method' => 'cash',
                    'concept' => "Reembolso {$payment->payment_number}: {$reason}",
                    'created_by_user_id' => $userId,
                    'created_at' => now(),
                ]);

                $cashSession->update([
                    'expected_cash' => $cashSession->expected_cash - $amount,
                ]);
            }

            return $refund;
        });
    }

    /**
     * Generate sequential credit note number (NC-00001).
     */
    public function generateCreditNoteNumber(): string
    {
        $count = CreditAdjustment::count() + 1;

        do {
            $formatted = sprintf('NC-%05d', $count);
            $exists = CreditAdjustment::where('credit_note_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }

    /**
     * Create an auditable credit adjustment / credit note for a patient charge (FIN-05).
     */
    public function createCreditAdjustment(
        PatientCharge $charge,
        float $amount,
        string $type,
        string $reason,
        ?string $userId = null
    ): CreditAdjustment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto de la nota de crédito debe ser mayor a cero.');
        }

        if ($amount > $charge->balance_due) {
            throw new InvalidArgumentException('El monto del ajuste excede el saldo pendiente del cargo.');
        }

        if (! in_array($type, ['subsequent_discount', 'correction', 'uncollectible', 'store_credit', 'reversal'], true)) {
            throw new InvalidArgumentException('El tipo de nota de crédito no es válido.');
        }

        return DB::connection('tenant')->transaction(function () use ($charge, $amount, $type, $reason, $userId) {
            $creditNoteNumber = $this->generateCreditNoteNumber();

            $adjustment = CreditAdjustment::create([
                'patient_charge_id' => $charge->id,
                'patient_id' => $charge->patient_id,
                'credit_note_number' => $creditNoteNumber,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'adjusted_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $newAdjusted = $charge->adjusted_amount + $amount;
            $newBalance = max(0.0, $charge->total_amount - $charge->paid_amount - $newAdjusted);
            $newStatus = $newBalance <= 0 ? 'paid' : ($charge->paid_amount > 0 ? 'partially_paid' : 'pending');

            $charge->update([
                'adjusted_amount' => $newAdjusted,
                'balance_due' => $newBalance,
                'status' => $newStatus,
            ]);

            return $adjustment;
        });
    }

    /**
     * Compute comprehensive patient account statement (FIN-06).
     *
     * @return array{
     *     patient: Patient,
     *     charges: Collection<int, PatientCharge>,
     *     payments: Collection<int, Payment>,
     *     adjustments: Collection<int, CreditAdjustment>,
     *     summary: array<string, float>
     * }
     */
    public function getPatientAccountStatement(Patient $patient): array
    {
        $charges = PatientCharge::with(['professional', 'treatmentPlanItem.procedure', 'allocations.payment', 'adjustments.createdBy'])
            ->where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::with(['splits', 'allocations.charge', 'refunds'])
            ->where('patient_id', $patient->id)
            ->orderBy('paid_at', 'desc')
            ->get();

        $adjustments = CreditAdjustment::with(['charge', 'createdBy'])
            ->where('patient_id', $patient->id)
            ->orderBy('adjusted_at', 'desc')
            ->get();

        $totalCharged = (float) $charges->sum('total_amount');
        $totalPaid = (float) $charges->sum('paid_amount');
        $totalAdjusted = (float) $charges->sum('adjusted_amount');
        $netBalanceDue = (float) $charges->sum('balance_due');
        $totalUnallocated = (float) $payments->sum('unallocated_amount');

        return [
            'patient' => $patient,
            'charges' => $charges,
            'payments' => $payments,
            'adjustments' => $adjustments,
            'summary' => [
                'total_charged' => $totalCharged,
                'total_paid' => $totalPaid,
                'total_adjusted' => $totalAdjusted,
                'net_balance_due' => $netBalanceDue,
                'unallocated_credit' => $totalUnallocated,
            ],
        ];
    }

    /**
     * Generate Aging Receivables (CxC) buckets report: 0-30, 31-60, 61-90, +90 days (FIN-06).
     *
     * @return array{
     *     buckets: array<string, array{label: string, total: float, charges: list<array<string, int|float|string|null>>}>,
     *     total_receivable: float,
     *     total_charges_count: int
     * }
     */
    public function getAgingReceivablesReport(): array
    {
        $now = Carbon::now();

        $pendingCharges = PatientCharge::with(['patient', 'professional'])
            ->where('balance_due', '>', 0)
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderBy('created_at')
            ->get();

        $buckets = [
            'current_30' => ['label' => '0 a 30 días (Corriente)', 'total' => 0.0, 'charges' => []],
            'aging_31_60' => ['label' => '31 a 60 días', 'total' => 0.0, 'charges' => []],
            'aging_61_90' => ['label' => '61 a 90 días', 'total' => 0.0, 'charges' => []],
            'over_90' => ['label' => 'Más de 90 días (Vencido)', 'total' => 0.0, 'charges' => []],
        ];

        foreach ($pendingCharges as $charge) {
            $daysOld = (int) $charge->created_at->diffInDays($now);
            $chargeData = [
                'id' => $charge->id,
                'charge_number' => $charge->charge_number,
                'patient_id' => $charge->patient_id,
                'patient_name' => $charge->patient->full_name,
                'patient_record' => $charge->patient->record_number,
                'patient_phone' => $charge->patient->phone,
                'concept' => $charge->concept,
                'total_amount' => $charge->total_amount,
                'paid_amount' => $charge->paid_amount,
                'adjusted_amount' => $charge->adjusted_amount,
                'balance_due' => $charge->balance_due,
                'days_old' => $daysOld,
                'created_at' => $charge->created_at->format('Y-m-d'),
                'due_date' => $charge->due_date?->format('Y-m-d'),
            ];

            if ($daysOld <= 30) {
                $buckets['current_30']['total'] += $charge->balance_due;
                $buckets['current_30']['charges'][] = $chargeData;
            } elseif ($daysOld <= 60) {
                $buckets['aging_31_60']['total'] += $charge->balance_due;
                $buckets['aging_31_60']['charges'][] = $chargeData;
            } elseif ($daysOld <= 90) {
                $buckets['aging_61_90']['total'] += $charge->balance_due;
                $buckets['aging_61_90']['charges'][] = $chargeData;
            } else {
                $buckets['over_90']['total'] += $charge->balance_due;
                $buckets['over_90']['charges'][] = $chargeData;
            }
        }

        $totalReceivable = array_sum(array_column($buckets, 'total'));

        return [
            'buckets' => $buckets,
            'total_receivable' => $totalReceivable,
            'total_charges_count' => $pendingCharges->count(),
        ];
    }
}
