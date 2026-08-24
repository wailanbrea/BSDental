<?php

namespace App\Core\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\Branch;
use App\Core\Models\CashSession;
use App\Core\Models\FollowUpTask;
use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\Professional;
use App\Core\Models\StockMovement;
use App\Http\Controllers\Controller;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClinicDashboardController extends Controller
{
    /**
     * Display clinic dashboard tailored to the authenticated user's role and branch scope.
     */
    public function index(Request $request, TenantContext $context): Response
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        /** @var ClinicProfile|null $profile */
        $profile = ClinicProfile::first();
        $tenant = $context->current();

        $defaultName = $tenant !== null ? $tenant->name : 'Clínica Dental';
        $defaultTrade = $tenant !== null ? $tenant->name : 'BSDental Clinic';

        $name = ($profile !== null && $profile->legal_name !== null)
            ? $profile->legal_name
            : $defaultName;

        $tradeName = ($profile !== null && $profile->trade_name !== null)
            ? $profile->trade_name
            : $defaultTrade;

        $currency = $profile !== null ? $profile->currency : 'USD';
        $timezone = $profile !== null ? $profile->timezone : 'UTC';

        $today = Carbon::today();
        $branchId = $request->input('branch_id');
        $branchIds = $user->branchScopeIds();

        $userRoles = $user->roles->pluck('name')->toArray();
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Detect primary dashboard profile
        $primaryRole = 'Restricted';
        if (in_array('Owner', $userRoles, true) || in_array('ClinicDirector', $userRoles, true)) {
            $primaryRole = 'Owner';
        } elseif (in_array('InventoryManager', $userRoles, true)) {
            $primaryRole = 'InventoryManager';
        } elseif (in_array('Cashier', $userRoles, true)) {
            $primaryRole = 'Cashier';
        } elseif (in_array('GeneralDentist', $userRoles, true) || in_array('SpecialistDentist', $userRoles, true)) {
            $primaryRole = 'Dentist';
        } elseif (in_array('Receptionist', $userRoles, true)) {
            $primaryRole = 'Receptionist';
        } elseif (in_array('LabTechnician', $userRoles, true)) {
            $primaryRole = 'LabTechnician';
        }

        // Base branches
        $branches = Branch::where('is_active', true)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
            ->select('id', 'name', 'is_main')
            ->orderByDesc('is_main')
            ->get();

        // 1. INVENTORY DATA (for InventoryManager or Owner)
        $inventoryData = [];
        if ($primaryRole === 'InventoryManager') {
            $totalItems = InventoryItem::where('is_active', true)->count();
            $lowStockItemsList = InventoryItem::where('is_active', true)
                ->with(['category:id,name', 'batches' => fn ($q) => $q->where('current_quantity', '>', 0)])
                ->get()
                ->filter(fn (InventoryItem $item) => $item->batches->sum('current_quantity') <= $item->min_stock)
                ->map(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'category' => $item->category->name ?? 'General',
                    'current_stock' => $item->batches->sum('current_quantity'),
                    'min_stock' => $item->min_stock,
                    'unit' => $item->unit,
                ])
                ->values();

            $expiringBatches = InventoryBatch::where('current_quantity', '>', 0)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now()->addDays(60))
                ->with('item:id,name,sku')
                ->orderBy('expires_at')
                ->take(8)
                ->get()
                ->map(fn (InventoryBatch $b) => [
                    'id' => $b->id,
                    'item_name' => $b->item->name ?? 'Insumo',
                    'lot_number' => $b->batch_number,
                    'quantity' => $b->current_quantity,
                    'expiry_date' => $b->expires_at->format('Y-m-d'),
                    'days_remaining' => (int) Carbon::now()->diffInDays($b->expires_at, false),
                ]);

            $recentMovements = StockMovement::with(['item:id,name,sku', 'createdBy:id,name'])
                ->orderByDesc('created_at')
                ->take(10)
                ->get()
                ->map(fn (StockMovement $m) => [
                    'id' => $m->id,
                    'item_name' => $m->item->name ?? 'Insumo',
                    'type' => $m->type,
                    'quantity' => $m->quantity,
                    'previous_stock' => $m->previous_stock,
                    'new_stock' => $m->new_stock,
                    'notes' => $m->notes,
                    'user_name' => $m->createdBy->name ?? 'Sistema',
                    'created_at' => Carbon::parse($m->created_at)->format('d/m H:i'),
                ]);

            $inventoryData = [
                'total_items' => $totalItems,
                'low_stock_count' => $lowStockItemsList->count(),
                'expiring_batches_count' => $expiringBatches->count(),
                'critical_items' => $lowStockItemsList->take(6),
                'expiring_batches' => $expiringBatches,
                'recent_movements' => $recentMovements,
            ];
        }

        // 2. CASHIER DATA (for Cashier or Owner)
        $cashierData = [];
        if ($primaryRole === 'Cashier') {
            $activeSession = CashSession::where('status', 'open')
                ->with(['cashRegister:id,name,branch_id', 'openedBy:id,name'])
                ->latest('opened_at')
                ->first();

            $cashCollectedToday = (float) Payment::whereDate('paid_at', $today)
                ->where('status', 'completed')
                ->sum('total_amount');

            $pendingCharges = PatientCharge::whereIn('status', ['pending', 'partially_paid'])
                ->with('patient:id,first_name,last_name,record_number')
                ->orderByDesc('created_at')
                ->take(8)
                ->get()
                ->map(fn (PatientCharge $c) => [
                    'id' => $c->id,
                    'charge_number' => $c->charge_number,
                    'patient_name' => $c->patient->full_name,
                    'patient_record' => $c->patient->record_number,
                    'concept' => $c->concept,
                    'total_amount' => $c->total_amount,
                    'balance_due' => $c->balance_due,
                    'status' => $c->status,
                ]);

            $recentPayments = Payment::whereDate('paid_at', $today)
                ->where('status', 'completed')
                ->with(['patient:id,first_name,last_name,record_number', 'splits'])
                ->orderByDesc('paid_at')
                ->take(8)
                ->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->id,
                    'receipt_number' => $p->payment_number,
                    'patient_name' => $p->patient->full_name,
                    'total_amount' => $p->total_amount,
                    'methods' => $p->splits->pluck('method')->join(', '),
                    'time' => $p->paid_at->format('h:i A'),
                ]);

            $cashierData = [
                'has_open_session' => $activeSession !== null,
                'active_session_name' => $activeSession?->cashRegister->name,
                'opening_amount' => $activeSession->opening_balance ?? 0,
                'collected_today' => $cashCollectedToday,
                'pending_charges_count' => PatientCharge::whereIn('status', ['pending', 'partially_paid'])->count(),
                'recent_payments' => $recentPayments,
                'pending_charges' => $pendingCharges,
            ];
        }

        // 3. DOCTOR DATA (for Dentist or Owner)
        $dentistData = [];
        if ($primaryRole === 'Dentist') {
            $professional = Professional::where('user_id', $user->id)->first();
            $dentistAppQuery = Appointment::whereDate('start_time', $today);
            if ($professional) {
                $dentistAppQuery->where('professional_id', $professional->id);
            }

            $myAppointments = (clone $dentistAppQuery)
                ->with(['patient', 'room', 'appointmentType'])
                ->orderBy('start_time')
                ->get()
                ->map(fn (Appointment $app) => [
                    'id' => $app->id,
                    'time' => $app->start_time->format('h:i A'),
                    'patient_id' => $app->patient_id,
                    'patient_name' => $app->patient->full_name,
                    'patient_record' => $app->patient->record_number,
                    'reason' => $app->reason ?? ($app->appointmentType->name ?? 'Consulta'),
                    'room_name' => $app->room->name ?? 'Sillón General',
                    'status' => $app->status,
                ]);

            $myPendingLabs = LabOrder::whereIn('status', ['sent', 'in_progress'])
                ->when(! $user->hasPermissionTo('lab.view'), fn ($q) => $q->whereRaw('1 = 0'))
                ->with(['patient:id,first_name,last_name,record_number', 'laboratory:id,name'])
                ->orderBy('due_date')
                ->take(6)
                ->get()
                ->map(fn (LabOrder $l) => [
                    'id' => $l->id,
                    'order_number' => $l->order_number,
                    'patient_name' => $l->patient->full_name,
                    'lab_name' => $l->laboratory->name,
                    'work_type' => $l->work_description,
                    'status' => $l->status,
                    'due_date' => $l->due_date?->format('Y-m-d') ?? 'Pendiente',
                ]);

            $dentistData = [
                'my_appointments_today' => $myAppointments->count(),
                'patients_waiting' => $myAppointments->where('status', 'waiting')->count(),
                'patients_completed' => $myAppointments->where('status', 'completed')->count(),
                'pending_lab_count' => $myPendingLabs->count(),
                'my_appointments' => $myAppointments,
                'my_lab_orders' => $myPendingLabs,
            ];
        }

        // 4. RECEPTIONIST DATA (for Receptionist or Owner)
        $receptionData = [];
        if ($primaryRole === 'Receptionist') {
            $followUps = FollowUpTask::where('status', 'pending')
                ->whereDate('due_date', '<=', $today)
                ->with('patient:id,first_name,last_name,phone,record_number')
                ->orderBy('due_date')
                ->take(8)
                ->get()
                ->map(fn (FollowUpTask $t) => [
                    'id' => $t->id,
                    'patient_name' => $t->patient->full_name,
                    'patient_phone' => $t->patient->phone,
                    'title' => $t->title,
                    'channel' => $t->type,
                    'due_date' => $t->due_date->format('Y-m-d'),
                ]);

            $receptionData = [
                'follow_ups_due' => $followUps,
                'unconfirmed_count' => Appointment::whereDate('start_time', Carbon::tomorrow())->where('status', 'scheduled')->count(),
            ];
        }

        // 5. GLOBAL / EXECUTIVE DATA (for Owner, ClinicDirector or general overview)
        $showGeneralDashboard = in_array($primaryRole, ['Owner', 'Receptionist'], true);
        $canViewAppointments = $showGeneralDashboard && $user->hasPermissionTo('appointments.view');
        $canViewPayments = $showGeneralDashboard && $user->hasAnyPermission(['payments.view', 'finance.view', 'finance.reports']);
        $canViewInventory = $showGeneralDashboard && $user->hasPermissionTo('inventory.view');
        $canViewLab = $showGeneralDashboard && $user->hasPermissionTo('lab.view');

        $appointmentsToday = 0;
        $attendedToday = 0;
        $todayAppointments = collect();

        $netCollectedToday = 0.0;
        $accountsReceivable = 0.0;
        if ($canViewAppointments) {
            $appQuery = Appointment::whereDate('start_time', $today);
            if ($branchId) {
                $appQuery->where('branch_id', $branchId);
            } elseif ($branchIds !== null) {
                $appQuery->whereIn('branch_id', $branchIds);
            }

            $appointmentsToday = (clone $appQuery)->count();
            $attendedToday = (clone $appQuery)->whereIn('status', ['in_progress', 'completed'])->count();

            $todayAppointments = (clone $appQuery)
                ->with(['patient', 'professional', 'room', 'appointmentType'])
                ->orderBy('start_time')
                ->take(10)
                ->get()
                ->map(fn (Appointment $app) => [
                    'id' => $app->id,
                    'time' => $app->start_time->format('h:i A'),
                    'patient_id' => $app->patient_id,
                    'patient_name' => $app->patient->full_name,
                    'patient_record' => $app->patient->record_number,
                    'reason' => $app->reason ?? ($app->appointmentType->name ?? 'Consulta Odontológica'),
                    'doctor_name' => "Dr. {$app->professional->last_name}",
                    'room_name' => $app->room->name ?? 'Sillón general',
                    'status' => $app->status,
                    'duration_minutes' => $app->duration_minutes,
                ]);
        }
        if ($canViewPayments) {
            $netCollectedToday = (float) Payment::whereDate('paid_at', $today)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->sum('total_amount');

            $accountsReceivable = (float) PatientCharge::whereIn('status', ['pending', 'partially_paid'])
                ->sum('balance_due');
        }

        $financialChart = [];
        if ($showGeneralDashboard && $user->hasPermissionTo('finance.reports')) {
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dayName = $date->locale('es')->isoFormat('ddd D');

                $production = (float) PatientCharge::whereDate('created_at', $date)->sum('total_amount');
                $collected = (float) Payment::whereDate('paid_at', $date)->whereNotIn('status', ['cancelled', 'refunded'])->sum('total_amount');

                $financialChart[] = [
                    'day' => ucfirst($dayName),
                    'date' => $date->format('Y-m-d'),
                    'production' => $production,
                    'collected' => $collected,
                ];
            }
        }

        $overdueAccountsCount = $canViewPayments ? PatientCharge::whereIn('status', ['pending', 'partially_paid'])
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->count() : 0;

        $lowStockCount = $canViewInventory ? InventoryItem::where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(current_quantity), 0) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id) <= min_stock')
            ->count() : 0;

        $pendingLabOrdersCount = $canViewLab ? LabOrder::whereIn('status', ['sent', 'in_progress'])->count() : 0;

        return Inertia::render('Clinic/Dashboard', [
            'clinic' => [
                'name' => $name,
                'trade_name' => $tradeName,
                'currency' => $currency,
                'timezone' => $timezone,
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'roles' => $userRoles,
                'permissions' => $userPermissions,
                'primary_role' => $primaryRole,
            ],
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_trend' => '+5%',
                'patients_attended_today' => $attendedToday,
                'attended_trend' => $attendedToday > 0 ? '+100%' : '0%',
                'net_collected_today' => $netCollectedToday,
                'collected_trend' => '+12%',
                'accounts_receivable' => $accountsReceivable,
            ],
            'todayAppointments' => $todayAppointments,
            'financialChart' => $financialChart,
            'alerts' => [
                'overdue_accounts_count' => $overdueAccountsCount,
                'low_stock_count' => $lowStockCount,
                'pending_lab_orders_count' => $pendingLabOrdersCount,
            ],
            'branches' => $branches,
            'selectedBranchId' => $branchId,
            'inventoryData' => $inventoryData,
            'cashierData' => $cashierData,
            'dentistData' => $dentistData,
            'receptionData' => $receptionData,
        ]);
    }
}
