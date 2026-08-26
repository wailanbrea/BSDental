<?php

namespace App\Core\Services;

use App\Core\Models\Appointment;
use App\Core\Models\LabOrder;
use App\Core\Models\PatientCharge;
use App\Core\Models\Payment;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Models\Refund;
use App\Core\Models\Room;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentPlanItem;
use Carbon\Carbon;

class AnalyticsReportingService
{
    /**
     * Get Executive Financial and Operational KPIs.
     *
     * @return array{
     *   production: float,
     *   gross_collected: float,
     *   refunds: float,
     *   net_collected: float,
     *   receivables: float,
     *   direct_material_costs: float,
     *   direct_lab_costs: float,
     *   professional_commissions: float,
     *   contribution_margin: float,
     *   net_cash_flow: float
     * }
     */
    public function getExecutiveKpis(Carbon $startDate, Carbon $endDate): array
    {
        $production = (float) TreatmentPlanItem::where('status', 'completed')
            ->whereBetween('completed_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('price');

        $grossCollected = (float) Payment::whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('total_amount');

        $refunds = (float) Refund::whereBetween('refunded_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('amount');

        $netCollected = $grossCollected - $refunds;

        $receivables = (float) PatientCharge::whereIn('status', ['pending', 'partially_paid'])
            ->sum('balance_due');

        $directMaterialCosts = (float) StockMovement::whereIn('type', ['consumption', 'procedure_consumption'])
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('total_cost');

        $directLabCosts = (float) LabOrder::whereIn('status', ['received', 'delivered'])
            ->whereBetween('updated_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('final_cost');

        $commissions = (float) ProfessionalCompensation::whereBetween('accrued_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->sum('commission_amount');

        $contributionMargin = $production - ($directMaterialCosts + $directLabCosts + $commissions);

        return [
            'production' => $production,
            'gross_collected' => $grossCollected,
            'refunds' => $refunds,
            'net_collected' => $netCollected,
            'receivables' => $receivables,
            'direct_material_costs' => $directMaterialCosts,
            'direct_lab_costs' => $directLabCosts,
            'professional_commissions' => $commissions,
            'contribution_margin' => $contributionMargin,
            'net_cash_flow' => $netCollected,
        ];
    }

    /**
     * Get Productivity by Professional.
     *
     * @return list<array{
     *   id: string,
     *   name: string,
     *   specialty: string,
     *   completed_appointments: int,
     *   completed_procedures: int,
     *   production_value: float,
     *   commissions_accrued: float
     * }>
     */
    public function getDoctorProductivity(Carbon $startDate, Carbon $endDate): array
    {
        $professionals = Professional::with('specialties')->where('is_active', true)->get();
        $results = [];

        foreach ($professionals as $prof) {
            $completedAppointments = Appointment::where('professional_id', $prof->id)
                ->where('status', 'completed')
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->count();

            $completedProcedures = PatientCharge::where('professional_id', $prof->id)
                ->whereNotNull('treatment_plan_item_id')
                ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->count();

            $productionValue = (float) PatientCharge::where('professional_id', $prof->id)
                ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('amount');

            $commissionsAccrued = (float) ProfessionalCompensation::where('professional_id', $prof->id)
                ->whereBetween('accrued_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->sum('commission_amount');

            $results[] = [
                'id' => $prof->id,
                'name' => $prof->full_name,
                'specialty' => $prof->specialties->pluck('name')->join(', ') ?: 'Odontología General',
                'completed_appointments' => $completedAppointments,
                'completed_procedures' => $completedProcedures,
                'production_value' => $productionValue,
                'commissions_accrued' => $commissionsAccrued,
            ];
        }

        return $results;
    }

    /**
     * Get Accounts Receivable Aging Matrix.
     *
     * @return array{
     *   current_0_30: float,
     *   aging_31_60: float,
     *   aging_61_90: float,
     *   aging_over_90: float,
     *   total_receivable: float
     * }
     */
    public function getReceivablesAging(): array
    {
        $charges = PatientCharge::whereIn('status', ['pending', 'partially_paid'])->get();

        $c0_30 = 0.0;
        $c31_60 = 0.0;
        $c61_90 = 0.0;
        $c90_plus = 0.0;

        $now = Carbon::now();

        foreach ($charges as $chg) {
            $days = $chg->created_at ? (int) $chg->created_at->diffInDays($now) : 0;
            $due = (float) $chg->balance_due;

            if ($days <= 30) {
                $c0_30 += $due;
            } elseif ($days <= 60) {
                $c31_60 += $due;
            } elseif ($days <= 90) {
                $c61_90 += $due;
            } else {
                $c90_plus += $due;
            }
        }

        return [
            'current_0_30' => $c0_30,
            'aging_31_60' => $c31_60,
            'aging_61_90' => $c61_90,
            'aging_over_90' => $c90_plus,
            'total_receivable' => $c0_30 + $c31_60 + $c61_90 + $c90_plus,
        ];
    }

    /**
     * Get Dental Chair / Room Occupancy Metrics.
     *
     * @return list<array{
     *   id: string,
     *   name: string,
     *   branch_name: string,
     *   total_appointments: int,
     *   occupied_minutes: int
     * }>
     */
    public function getChairOccupancy(Carbon $startDate, Carbon $endDate): array
    {
        $rooms = Room::with('branch')->where('is_active', true)->get();
        $results = [];

        foreach ($rooms as $room) {
            $appointments = Appointment::where('room_id', $room->id)
                ->whereIn('status', ['completed', 'in_progress', 'scheduled', 'confirmed'])
                ->whereBetween('start_time', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
                ->get();

            $totalMinutes = (int) $appointments->sum('duration_minutes');

            $results[] = [
                'id' => $room->id,
                'name' => $room->name,
                'branch_name' => $room->branch->name ?? 'Central',
                'total_appointments' => $appointments->count(),
                'occupied_minutes' => $totalMinutes,
            ];
        }

        return $results;
    }
}
