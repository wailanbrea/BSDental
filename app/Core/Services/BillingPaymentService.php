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
        float $amount,
        ?string $userId = null,
        ?string $idempotencyKey = null,
        ?string $reason = null
    ): PaymentAllocation {
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto a asignar debe ser mayor a cero.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $charge, $amount, $userId, $idempotencyKey, $reason) {
            // Lock in a consistent order so stale models cannot oversubscribe a payment or charge.
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedCharge = PatientCharge::whereKey($charge->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = PaymentAllocation::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->payment_id !== $lockedPayment->id
                        || $existing->patient_charge_id !== $lockedCharge->id
                        || round((float) $existing->amount, 2) !== round($amount, 2)) {
                        throw new InvalidArgumentException('Clave de idempotencia ya utilizada con parámetros diferentes.');
                    }

                    return $existing;
                }
            }

            if ($lockedPayment->patient_id !== $lockedCharge->patient_id) {
                throw new InvalidArgumentException('El pago y el cargo deben pertenecer al mismo paciente.');
            }

            $allocatedAmount = (float) PaymentAllocation::where('payment_id', $lockedPayment->id)
                ->selectRaw('COALESCE(SUM(amount - reversed_amount), 0) as total')
                ->value('total');
            $availableAmount = round($lockedPayment->total_amount - $lockedPayment->refunded_amount - $allocatedAmount, 2);

            if ($amount > $availableAmount) {
                throw new InvalidArgumentException('El monto excede el saldo no asignado disponible en este pago.');
            }

            if ($amount > $lockedCharge->balance_due) {
                throw new InvalidArgumentException('El monto excede el saldo pendiente del cargo seleccionado.');
            }

            $allocation = PaymentAllocation::create([
                'payment_id' => $lockedPayment->id,
                'patient_charge_id' => $lockedCharge->id,
                'amount' => $amount,
                'reversed_amount' => 0.00,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'allocated_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $newAllocated = round($allocatedAmount + $amount, 2);
            $newUnallocated = round($lockedPayment->total_amount - $lockedPayment->refunded_amount - $newAllocated, 2);
            $paymentStatus = $newUnallocated <= 0 ? 'fully_allocated' : 'partially_allocated';

            $lockedPayment->update([
                'allocated_amount' => $newAllocated,
                'unallocated_amount' => $newUnallocated,
                'status' => $paymentStatus,
            ]);

            $newPaid = round($lockedCharge->paid_amount + $amount, 2);
            $newBalance = round(max(0.0, $lockedCharge->total_amount - $lockedCharge->adjusted_amount - $newPaid), 2);
            $chargeStatus = $newBalance <= 0 ? 'paid' : 'partially_paid';

            $lockedCharge->update([
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
        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del reembolso debe ser mayor a cero.');
        }

        return DB::connection('tenant')->transaction(function () use ($payment, $amount, $reason, $cashSession, $userId, $idempotencyKey) {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Check after the payment lock so concurrent replays observe the committed refund.
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $existing = Refund::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    if ($existing->payment_id !== $lockedPayment->id || (float) $existing->amount !== (float) $amount) {
                        throw new InvalidArgumentException('Clave de idempotencia ya utilizada con parámetros diferentes.');
                    }

                    return $existing;
                }
            }

            if ($amount > $lockedPayment->getRefundableBalance()) {
                throw new InvalidArgumentException('El monto del reembolso excede el saldo reembolsable disponible.');
            }

            $lockedCashSession = null;
            if ($cashSession) {
                $lockedCashSession = CashSession::whereKey($cashSession->id)->lockForUpdate()->firstOrFail();
                if ($lockedCashSession->status !== 'open') {
                    throw new InvalidArgumentException('La sesión de caja seleccionada no está abierta.');
                }
            }

            $allocations = PaymentAllocation::where('payment_id', $lockedPayment->id)
                ->whereRaw('amount > reversed_amount')
                ->orderByDesc('allocated_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();
            $chargeIds = $allocations->pluck('patient_charge_id')->unique()->sort()->values();
            $charges = PatientCharge::whereIn('id', $chargeIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $allocatedAmount = (float) $allocations->sum(fn (PaymentAllocation $allocation) => $allocation->getOpenAmount());
            $unallocatedAmount = round($lockedPayment->total_amount - $lockedPayment->refunded_amount - $allocatedAmount, 2);
            $amountToReverse = max(0.0, round($amount - $unallocatedAmount, 2));

            // Refund unallocated credit first, then reverse the most recent allocations with a full audit trail.
            foreach ($allocations as $allocation) {
                if ($amountToReverse <= 0) {
                    break;
                }

                $reversal = min($allocation->getOpenAmount(), $amountToReverse);
                $allocation->update(['reversed_amount' => round($allocation->reversed_amount + $reversal, 2)]);

                $lockedCharge = $charges->get($allocation->patient_charge_id);
                if ($lockedCharge === null) {
                    throw new InvalidArgumentException('No se encontró el cargo asociado a la asignación.');
                }

                $newPaid = round($lockedCharge->paid_amount - $reversal, 2);
                $newBalance = round(max(0.0, $lockedCharge->total_amount - $lockedCharge->adjusted_amount - $newPaid), 2);
                $lockedCharge->update([
                    'paid_amount' => $newPaid,
                    'balance_due' => $newBalance,
                    'status' => $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'pending'),
                ]);
                $amountToReverse = round($amountToReverse - $reversal, 2);
            }

            if ($amountToReverse > 0) {
                throw new InvalidArgumentException('El monto del reembolso excede el saldo conciliado disponible.');
            }

            $refund = Refund::create([
                'payment_id' => $lockedPayment->id,
                'patient_id' => $lockedPayment->patient_id,
                'cash_session_id' => $lockedCashSession?->id,
                'amount' => $amount,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
                'refunded_at' => now(),
                'created_by_user_id' => $userId,
            ]);

            $newRefundedAmount = round($lockedPayment->refunded_amount + $amount, 2);
            $newAllocatedAmount = round($allocatedAmount - max(0.0, $amount - $unallocatedAmount), 2);
            $newUnallocatedAmount = round($lockedPayment->total_amount - $newRefundedAmount - $newAllocatedAmount, 2);
            $paymentStatus = $newRefundedAmount >= $lockedPayment->total_amount
                ? 'refunded'
                : ($newUnallocatedAmount <= 0 ? 'fully_allocated' : ($newAllocatedAmount > 0 ? 'partially_allocated' : 'confirmed'));

            $lockedPayment->update([
                'allocated_amount' => $newAllocatedAmount,
                'unallocated_amount' => $newUnallocatedAmount,
                'refunded_amount' => $newRefundedAmount,
                'status' => $paymentStatus,
            ]);

            if ($lockedCashSession) {
                CashMovement::create([
                    'cash_session_id' => $lockedCashSession->id,
                    'type' => 'patient_refund',
                    'amount' => -$amount,
                    'payment_method' => 'cash',
                    'concept' => "Reembolso {$lockedPayment->payment_number}: {$reason}",
                    'created_by_user_id' => $userId,
                    'created_at' => now(),
                ]);

                $lockedCashSession->update([
                    'expected_cash' => $lockedCashSession->expected_cash - $amount,
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
        $payerBalance = max(0.0, round($netBalanceDue - $totalUnallocated, 2));
        $saldoAFavor = max(0.0, round($totalUnallocated - $netBalanceDue, 2));

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
                // Product decision: credit is displayed as a manual-only balance; it is never auto-applied to charges.
                'payer_balance' => $payerBalance,
                'customer_credit' => $totalUnallocated,
                'saldo_a_favor' => $saldoAFavor,
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
