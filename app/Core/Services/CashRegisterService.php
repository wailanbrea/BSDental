<?php

namespace App\Core\Services;

use App\Core\Auth\Models\User;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use InvalidArgumentException;

class CashRegisterService
{
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

        $session->update([
            'status' => 'open',
            'closed_at' => null,
            'closing_notes' => "Reabierta por {$user->name}: {$reason}",
        ]);

        return $session;
    }
}
