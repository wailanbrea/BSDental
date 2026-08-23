<?php

namespace App\Core\Controllers;

use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\Branch;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
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
     * Display clinic dashboard with full clinical command center metrics.
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

        // Base queries
        $appQuery = Appointment::whereDate('start_time', $today);
        if ($branchId) {
            $appQuery->where('branch_id', $branchId);
        }

        $appointmentsToday = (clone $appQuery)->count();
        $attendedToday = (clone $appQuery)->whereIn('status', ['in_progress', 'completed'])->count();

        $netCollectedToday = (float) Payment::whereDate('paid_at', $today)
            ->where('status', 'completed')
            ->sum('total_amount');

        $accountsReceivable = (float) PatientCharge::whereIn('status', ['pending', 'partially_paid'])
            ->sum('balance_due');

        // Today Appointments list
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

        // Financial 7-Day Chart Data
        $financialChart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayName = $date->locale('es')->isoFormat('ddd D');

            $production = (float) PatientCharge::whereDate('created_at', $date)->sum('total_amount');
            $collected = (float) Payment::whereDate('paid_at', $date)->where('status', 'completed')->sum('total_amount');

            $financialChart[] = [
                'day' => ucfirst($dayName),
                'date' => $date->format('Y-m-d'),
                'production' => $production,
                'collected' => $collected,
            ];
        }

        // Operational Alerts
        $overdueAccountsCount = PatientCharge::whereIn('status', ['pending', 'partially_paid'])
            ->where('created_at', '<', Carbon::now()->subDays(30))
            ->count();

        $lowStockCount = InventoryItem::where('is_active', true)
            ->whereRaw('(SELECT COALESCE(SUM(current_quantity), 0) FROM inventory_batches WHERE inventory_batches.inventory_item_id = inventory_items.id) <= min_stock')
            ->count();

        $pendingLabOrdersCount = LabOrder::whereIn('status', ['sent', 'in_progress'])->count();

        $branches = Branch::where('is_active', true)
            ->select('id', 'name', 'is_main')
            ->orderByDesc('is_main')
            ->get();

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
            'today_appointments' => $todayAppointments,
            'financial_chart' => $financialChart,
            'alerts' => [
                'overdue_accounts_count' => $overdueAccountsCount,
                'low_stock_count' => $lowStockCount,
                'pending_lab_orders_count' => $pendingLabOrdersCount,
            ],
            'branches' => $branches,
            'selected_branch_id' => $branchId,
        ]);
    }
}
