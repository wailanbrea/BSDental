<?php

namespace App\Core\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Services\CashRegisterService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    public function __construct(
        protected CashRegisterService $cashService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display cash registers and sessions dashboard.
     */
    public function index(): Response
    {
        $registers = CashRegister::with(['branch', 'sessions' => function ($q) {
            $q->orderBy('opened_at', 'desc')->limit(5);
        }])->get();

        $activeSession = CashSession::with(['cashRegister', 'openedBy', 'movements'])
            ->where('status', 'open')
            ->first();

        return Inertia::render('Clinic/Cash/Index', [
            'registers' => $registers,
            'activeSession' => $activeSession,
        ]);
    }

    /**
     * Open cash session.
     */
    public function open(Request $request, string $registerId): RedirectResponse
    {
        $register = CashRegister::findOrFail($registerId);

        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $session = $this->cashService->openSession(
            $register,
            $user,
            $validated['opening_balance']
        );

        $this->auditLogger->logTenant('cash.session_opened', 'CashSession', $session->id, [
            'register_id' => $register->id,
            'opening_balance' => $session->opening_balance,
        ]);

        return redirect()->back()->with('success', "Caja {$register->name} abierta con fondo inicial de \${$session->opening_balance}.");
    }

    /**
     * Close cash session.
     */
    public function close(Request $request, string $sessionId): RedirectResponse
    {
        $session = CashSession::findOrFail($sessionId);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $closed = $this->cashService->closeSession(
            $session,
            $user,
            $validated['counted_cash'],
            $validated['closing_notes'] ?? null
        );

        $this->auditLogger->logTenant('cash.session_closed', 'CashSession', $closed->id, [
            'counted_cash' => $closed->counted_cash,
            'expected_cash' => $closed->expected_cash,
            'difference' => $closed->difference,
        ]);

        return redirect()->back()->with('success', "Caja cerrada. Descuadre registrado: \${$closed->difference}.");
    }
}
