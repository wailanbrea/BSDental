<?php

namespace App\Core\Auth\Middleware;

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Models\Room;
use App\Core\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantBranchAccess
{
    /**
     * Reject explicit branch-scoped requests outside the user's assignments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user('web');
        if (! $user || $user->hasRole('Owner')) {
            return $next($request);
        }

        $allowed = $user->branchScopeIds();
        if ($allowed === null) {
            return $next($request);
        }

        $requested = [];
        $branch = $request->route('branch');
        if ($branch instanceof Branch) {
            $requested[] = $branch->id;
        } elseif (is_string($branch)) {
            $requested[] = $branch;
        }

        $room = $request->route('room');
        if ($room instanceof Room) {
            $requested[] = $room->branch_id;
        }

        $routeId = $request->route('id');
        if (is_string($routeId)) {
            if ($request->is('cash-registers/*')) {
                $registerBranch = CashRegister::whereKey($routeId)->value('branch_id');
                if (is_string($registerBranch)) {
                    $requested[] = $registerBranch;
                }
            } elseif ($request->is('cash-sessions/*')) {
                $sessionBranch = CashSession::query()->whereKey($routeId)
                    ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                    ->value('cash_registers.branch_id');
                if (is_string($sessionBranch)) {
                    $requested[] = $sessionBranch;
                }
            }
        }

        $inputBranch = $request->input('branch_id');
        if (is_string($inputBranch) && $inputBranch !== '') {
            $requested[] = $inputBranch;
        }

        $inputBranches = $request->input('branch_ids', []);
        if (is_array($inputBranches)) {
            $requested = [...$requested, ...array_filter($inputBranches, 'is_string')];
        }

        $warehouseId = $request->input('warehouse_id');
        if (is_string($warehouseId) && $warehouseId !== '') {
            $warehouseBranch = Warehouse::whereKey($warehouseId)->value('branch_id');
            if (is_string($warehouseBranch)) {
                $requested[] = $warehouseBranch;
            }
        }

        foreach (array_unique($requested) as $branchId) {
            if (! in_array($branchId, $allowed, true)) {
                abort(403, 'No tiene acceso a la sucursal seleccionada.');
            }
        }

        return $next($request);
    }
}
