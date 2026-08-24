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
     * Display cash registers and sessions dashboard (multi-register, multi-session aware).
     */
    public function index(): Response
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user?->branchScopeIds();

        $registers = CashRegister::with([
            'branch',
            'activeSession.openedBy',
            'activeSession.movements.createdBy',
            'sessions' => function ($q) {
                $q->with(['openedBy', 'closedBy'])->orderBy('opened_at', 'desc')->limit(10);
            },
        ])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get();

        $activeSessions = CashSession::with(['cashRegister.branch', 'openedBy', 'movements.createdBy'])
            ->where('status', 'open')
            ->when($branchIds !== null, fn ($query) => $query->whereHas('cashRegister', fn ($rq) => $rq->whereIn('branch_id', $branchIds)))
            ->orderBy('opened_at', 'desc')
            ->get();

        $primaryActiveSession = $activeSessions->first();

        return Inertia::render('Clinic/Cash/Index', [
            'registers' => $registers,
            'activeSessions' => $activeSessions,
            'activeSession' => $primaryActiveSession,
            'canReopen' => $user?->hasPermissionTo('cash.reopen') ?? false,
        ]);
    }

    /**
     * Open cash session.
     */
    public function open(Request $request, string $registerId): RedirectResponse
    {
        $register = CashRegister::findOrFail($registerId);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user->branchScopeIds();

        if ($branchIds !== null && ! in_array($register->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para operar cajas en esta sucursal.');
        }

        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
        ]);

        $session = $this->cashService->openSession(
            $register,
            $user,
            (float) $validated['opening_balance']
        );

        $this->auditLogger->logTenant('cash.session_opened', 'CashSession', $session->id, [
            'register_id' => $register->id,
            'branch_id' => $register->branch_id,
            'opening_balance' => $session->opening_balance,
        ]);

        return redirect()->back()->with('success', "Caja {$register->name} abierta con fondo inicial de \${$session->opening_balance}.");
    }

    /**
     * Record an auditable manual income or expense.
     */
    public function storeMovement(Request $request, string $sessionId): RedirectResponse
    {
        $session = CashSession::with('cashRegister')->where('status', 'open')->findOrFail($sessionId);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user->branchScopeIds();

        if ($branchIds !== null && ! in_array($session->cashRegister->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para operar cajas en esta sucursal.');
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:manual_income,manual_expense'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:cash,credit_card,debit_card,transfer,zelle,insurance,check'],
            'concept' => ['required', 'string', 'max:255'],
        ]);

        $movement = $this->cashService->recordManualMovement(
            $session,
            $user,
            $validated['type'],
            (float) $validated['amount'],
            $validated['concept'],
            $validated['payment_method']
        );

        $this->auditLogger->logTenant('cash.manual_movement_recorded', 'CashMovement', $movement->id, [
            'cash_session_id' => $session->id,
            'type' => $movement->type,
            'amount' => $movement->amount,
            'payment_method' => $movement->payment_method,
        ]);

        return redirect()->back()->with('success', 'Movimiento de caja registrado correctamente.');
    }

    /**
     * Close cash session.
     */
    public function close(Request $request, string $sessionId): RedirectResponse
    {
        $session = CashSession::with('cashRegister')->findOrFail($sessionId);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user->branchScopeIds();

        if ($branchIds !== null && ! in_array($session->cashRegister->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para operar cajas en esta sucursal.');
        }

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        $closed = $this->cashService->closeSession(
            $session,
            $user,
            (float) $validated['counted_cash'],
            $validated['closing_notes'] ?? null
        );

        $this->auditLogger->logTenant('cash.session_closed', 'CashSession', $closed->id, [
            'cash_register_id' => $closed->cash_register_id,
            'counted_cash' => $closed->counted_cash,
            'expected_cash' => $closed->expected_cash,
            'difference' => $closed->difference,
        ]);

        return redirect()->back()->with('success', "Caja cerrada. Descuadre registrado: \${$closed->difference}.");
    }

    /**
     * Reopen a closed cash session (requires cash.reopen permission & mandatory reason).
     */
    public function reopen(Request $request, string $sessionId): RedirectResponse
    {
        $session = CashSession::with('cashRegister')->findOrFail($sessionId);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user->branchScopeIds();

        if ($branchIds !== null && ! in_array($session->cashRegister->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para operar cajas en esta sucursal.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $reopened = $this->cashService->reopenSession(
            $session,
            $user,
            $validated['reason']
        );

        $this->auditLogger->logTenant('cash.session_reopened', 'CashSession', $reopened->id, [
            'cash_register_id' => $reopened->cash_register_id,
            'reason' => $validated['reason'],
            'reopened_by' => $user->name,
        ]);

        return redirect()->back()->with('success', 'Sesión de caja reabierta correctamente para correcciones autorizadas.');
    }

    /**
     * Display detailed historic cash session audit & reconciliation report (FIN-03).
     */
    public function showSession(string $sessionId): Response
    {
        $session = CashSession::with([
            'cashRegister.branch',
            'openedBy',
            'closedBy',
            'movements.createdBy',
        ])->findOrFail($sessionId);

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user?->branchScopeIds();

        if ($branchIds !== null && ! in_array($session->cashRegister->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para ver los detalles de esta sesión de caja.');
        }

        $methodTotals = [
            'cash' => 0.0,
            'credit_card' => 0.0,
            'debit_card' => 0.0,
            'transfer' => 0.0,
            'zelle' => 0.0,
            'insurance' => 0.0,
            'check' => 0.0,
        ];

        $totalIncome = 0.0;
        $totalExpense = 0.0;

        foreach ($session->movements as $m) {
            $method = $m->payment_method;
            if (isset($methodTotals[$method])) {
                $methodTotals[$method] += (float) $m->amount;
            }

            if ($m->amount > 0) {
                $totalIncome += (float) $m->amount;
            } else {
                $totalExpense += (float) abs($m->amount);
            }
        }

        return Inertia::render('Clinic/Cash/Show', [
            'session' => $session,
            'methodTotals' => $methodTotals,
            'totalIncome' => $totalIncome,
            'totalExpense' => $totalExpense,
            'canReopen' => $user?->hasPermissionTo('cash.reopen') ?? false,
        ]);
    }

    /**
     * Export cash session movements to auditable CSV (FIN-03).
     */
    public function exportSession(string $sessionId)
    {
        $session = CashSession::with([
            'cashRegister.branch',
            'openedBy',
            'closedBy',
            'movements.createdBy',
        ])->findOrFail($sessionId);

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        $branchIds = $user?->branchScopeIds();

        if ($branchIds !== null && ! in_array($session->cashRegister->branch_id, $branchIds, true)) {
            abort(403, 'No tiene autorización para exportar esta sesión de caja.');
        }

        $this->auditLogger->logTenant('cash.session_exported', 'CashSession', $session->id, [
            'cash_register_id' => $session->cash_register_id,
            'movements_count' => $session->movements->count(),
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"arqueo_caja_{$session->id}.csv\"",
        ];

        $callback = function () use ($session) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ['REPORTE DE ARQUEO DE CAJA - BSDENTAL']);
            fputcsv($file, ['Caja', $session->cashRegister->name]);
            fputcsv($file, ['Sucursal', $session->cashRegister->branch?->name]);
            fputcsv($file, ['Estado', $session->status]);
            fputcsv($file, ['Apertura', $session->opened_at?->format('Y-m-d H:i:s'), 'Por', $session->openedBy?->name]);
            fputcsv($file, ['Cierre', $session->closed_at?->format('Y-m-d H:i:s'), 'Por', $session->closedBy?->name]);
            fputcsv($file, ['Fondo Inicial', number_format($session->opening_balance, 2)]);
            fputcsv($file, ['Efectivo Esperado', number_format($session->expected_cash, 2)]);
            fputcsv($file, ['Efectivo Contado', number_format((float) $session->counted_cash, 2)]);
            fputcsv($file, ['Diferencia / Descuadre', number_format($session->difference, 2)]);
            fputcsv($file, []);
            fputcsv($file, ['ID Movimiento', 'Fecha y Hora', 'Tipo', 'Concepto', 'Método de Pago', 'Usuario', 'Monto']);

            foreach ($session->movements as $m) {
                fputcsv($file, [
                    $m->id,
                    $m->created_at?->format('Y-m-d H:i:s'),
                    $m->type,
                    $m->concept,
                    $m->payment_method,
                    $m->createdBy?->name ?? 'Sistema',
                    number_format($m->amount, 2),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
