<?php

namespace App\Core\Services;

use App\Core\Auth\Models\User;
use App\Core\Models\CashMovement;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashRegisterService
{
    /**
     * Record a manual cash-flow movement without mixing non-cash methods into expected cash.
     */
    public function recordManualMovement(
        CashSession $session,
        User $user,
        string $type,
        float $amount,
        string $concept,
        string $paymentMethod = 'cash',
        ?string $idempotencyKey = null
    ): CashMovement {
        if ($session->status !== 'open') {
            throw new InvalidArgumentException('La sesión de caja seleccionada no está abierta.');
        }

        if (! in_array($type, ['manual_income', 'manual_expense'], true)) {
            throw new InvalidArgumentException('El tipo de movimiento manual no es válido.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('El monto del movimiento debe ser mayor a cero.');
        }

        $signedAmount = $type === 'manual_expense' ? -$amount : $amount;

        // Idempotency check
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = CashMovement::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ($existing->cash_session_id !== $session->id || (float) abs($existing->amount) !== (float) $amount) {
                    throw new InvalidArgumentException('Clave de idempotencia ya utilizada con parámetros diferentes.');
                }

                return $existing;
            }
        }

        return DB::connection('tenant')->transaction(function () use ($session, $user, $type, $signedAmount, $concept, $paymentMethod, $idempotencyKey) {
            $movement = CashMovement::create([
                'cash_session_id' => $session->id,
                'type' => $type,
                'amount' => $signedAmount,
                'payment_method' => $paymentMethod,
                'concept' => $concept,
                'idempotency_key' => $idempotencyKey,
                'created_by_user_id' => $user->id,
                'created_at' => now(),
            ]);

            if ($paymentMethod === 'cash') {
                $session->update([
                    'expected_cash' => $session->expected_cash + $signedAmount,
                ]);
            }

            return $movement;
        });
    }

    /**
     * Open a cash session.
     */
    public function openSession(
        CashRegister $register,
        User $user,
        float $openingBalance
    ): CashSession {
        // Check if register already has an open session
        $existingOpen = CashSession::where('cash_register_id', $register->id)
            ->where('status', 'open')
            ->first();

        if ($existingOpen) {
            throw new InvalidArgumentException('Esta caja ya cuenta con una sesión abierta.');
        }

        return CashSession::create([
            'cash_register_id' => $register->id,
            'opened_by_user_id' => $user->id,
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'expected_cash' => $openingBalance,
            'counted_cash' => null,
            'difference' => 0.00,
            'opened_at' => now(),
        ]);
    }

    /**
     * Close a cash session with blind count.
     */
    public function closeSession(
        CashSession $session,
        User $user,
        float $countedCash,
        ?string $notes = null
    ): CashSession {
        if ($session->status === 'closed') {
            throw new InvalidArgumentException('La sesión de caja ya se encuentra cerrada.');
        }

        $difference = $countedCash - $session->expected_cash;

        $session->update([
            'status' => 'closed',
            'closed_by_user_id' => $user->id,
            'counted_cash' => $countedCash,
            'difference' => $difference,
            'closed_at' => now(),
            'closing_notes' => $notes,
        ]);

        return $session;
    }

    /**
     * Reopen a closed cash session (requires reason & audit).
     */
    public function reopenSession(
        CashSession $session,
        User $user,
        string $reason
    ): CashSession {
        if ($session->status !== 'closed') {
            throw new InvalidArgumentException('Solo se pueden reabrir sesiones cerradas.');
        }

        $existingOpen = CashSession::where('cash_register_id', $session->cash_register_id)
            ->where('status', 'open')
            ->where('id', '!=', $session->id)
            ->exists();

        if ($existingOpen) {
            throw new InvalidArgumentException('Esta caja ya cuenta con una sesión abierta actualmente.');
        }

        $previousNotes = $session->closing_notes ? "{$session->closing_notes}\n" : '';
        $reopenEntry = "[REAPERTURA ".now()->format('Y-m-d H:i')." por {$user->name}]: {$reason}";

        $session->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closing_notes' => $previousNotes.$reopenEntry,
        ]);

        return $session;
    }
}
