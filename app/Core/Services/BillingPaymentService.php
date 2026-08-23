<?php

namespace App\Core\Services;

use App\Core\Models\CashMovement;
use App\Core\Models\CashSession;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\PaymentAllocation;
use App\Core\Models\PaymentSplit;
use App\Core\Models\Refund;
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
            'balance_due' => $totalAmount,
            'status' => 'pending',
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Record payment with multi-method splits.
     *
     * @param  list<array{method: string, amount: float, reference_code?: string|null}>  $splits
     */
    public function recordPayment(
        Patient $patient,
        array $splits,
        ?CashSession $cashSession = null,
        ?string $userId = null
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

        return DB::connection('tenant')->transaction(function () use ($patient, $splits, $totalAmount, $cashSession, $userId) {
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

            if ($cashSession) {
                CashMovement::create([
                    'cash_session_id' => $cashSession->id,
                    'type' => 'patient_payment',
                    'amount' => $totalAmount,
                    'payment_method' => $splits[0]['method'],
                    'concept' => "Cobro {$paymentNumber} - {$patient->full_name}",
                    'created_by_user_id' => $userId,
                    'created_at' => now(),
                ]);

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
            throw new InvalidArgumentException('El monto a asignar excede el saldo no asignado del pago.');
        }

        if ($amount > $charge->balance_due) {
            throw new InvalidArgumentException('El monto a asignar excede el saldo pendiente del cargo.');
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
     * Process a refund on a payment.
     */
    public function refundPayment(
        Payment $payment,
        float $amount,
        string $reason,
        ?CashSession $cashSession = null,
        ?string $userId = null
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

        return DB::connection('tenant')->transaction(function () use ($payment, $amount, $reason, $cashSession, $userId) {
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'patient_id' => $payment->patient_id,
                'cash_session_id' => $cashSession?->id,
                'amount' => $amount,
                'reason' => $reason,
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
}
