<?php

namespace App\Core\Services;

use App\Core\Models\Employee;
use App\Core\Models\PayrollItem;
use App\Core\Models\PayrollRun;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Models\TreatmentPlanItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayrollService
{
    public function accrueProcedureCommission(TreatmentPlanItem $item, Professional $professional): ?ProfessionalCompensation
    {
        $employee = Employee::query()
            ->where('professional_id', $professional->id)
            ->where('compensation_type', 'commission')
            ->where('status', 'active')
            ->first();

        if ($employee === null || $employee->commission_rate <= 0 || $item->completed_at === null) {
            return null;
        }

        return ProfessionalCompensation::firstOrCreate(
            ['treatment_plan_item_id' => $item->id],
            [
                'professional_id' => $professional->id,
                'rule_type' => 'percentage_production',
                'rate' => $employee->commission_rate,
                'base_amount' => $item->price,
                'commission_amount' => round($item->price * ($employee->commission_rate / 100), 2),
                'status' => 'accrued',
                'accrued_at' => $item->completed_at,
            ],
        );
    }

    public function generateMonthlyRun(Carbon $month, string $userId): PayrollRun
    {
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        return DB::connection('tenant')->transaction(function () use ($periodStart, $periodEnd, $userId) {
            if (PayrollRun::whereDate('period_start', $periodStart)->whereDate('period_end', $periodEnd)->exists()) {
                throw new InvalidArgumentException('Ya existe una nómina para este período.');
            }

            $run = PayrollRun::create([
                'run_number' => 'NOM-'.$periodStart->format('Ym').'-'.Str::upper(Str::random(6)),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => 'draft',
                'created_by_user_id' => $userId,
            ]);

            $fixedTotal = 0.0;
            $commissionTotal = 0.0;

            $employees = Employee::with('professional')->where('status', 'active')->orderBy('full_name')->get();

            foreach ($employees as $employee) {
                if ($employee->hire_date?->isAfter($periodEnd)) {
                    continue;
                }

                if ($employee->compensation_type === 'commission' && $employee->professional !== null) {
                    TreatmentPlanItem::query()
                        ->where('professional_id', $employee->professional_id)
                        ->where('status', 'completed')
                        ->whereBetween('completed_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
                        ->whereDoesntHave('compensation')
                        ->each(fn (TreatmentPlanItem $item) => $this->accrueProcedureCommission($item, $employee->professional));
                }

                $fixedSalary = $this->fixedSalaryForPeriod($employee, $periodStart, $periodEnd);
                $compensations = $employee->professional_id
                    ? ProfessionalCompensation::query()
                        ->where('professional_id', $employee->professional_id)
                        ->where('status', 'accrued')
                        ->whereBetween('accrued_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()])
                        ->get()
                    : collect();
                $commissions = round((float) $compensations->sum('commission_amount'), 2);

                $item = PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'fixed_salary_amount' => $fixedSalary,
                    'commission_amount' => $commissions,
                    'net_amount' => round($fixedSalary + $commissions, 2),
                    'status' => 'draft',
                    'employee_snapshot' => [
                        'employee_number' => $employee->employee_number,
                        'full_name' => $employee->full_name,
                        'position' => $employee->position,
                        'compensation_type' => $employee->compensation_type,
                        'monthly_salary' => $employee->monthly_salary,
                        'commission_rate' => $employee->commission_rate,
                    ],
                ]);

                if ($fixedSalary > 0) {
                    $item->lines()->create([
                        'type' => 'fixed_salary',
                        'description' => 'Sueldo fijo mensual',
                        'amount' => $fixedSalary,
                    ]);
                }

                foreach ($compensations as $compensation) {
                    $item->lines()->create([
                        'professional_compensation_id' => $compensation->id,
                        'type' => 'commission',
                        'description' => "Comisión {$compensation->rate}% sobre producción",
                        'amount' => $compensation->commission_amount,
                    ]);
                    $compensation->update(['status' => 'settled', 'settled_at' => now()]);
                }

                $fixedTotal += $fixedSalary;
                $commissionTotal += $commissions;
            }

            $run->update([
                'fixed_salary_total' => round($fixedTotal, 2),
                'commission_total' => round($commissionTotal, 2),
                'net_total' => round($fixedTotal + $commissionTotal, 2),
            ]);

            return $run->load(['items.employee', 'items.lines']);
        });
    }

    public function markPaid(PayrollRun $run, string $userId): PayrollRun
    {
        if ($run->status !== 'draft') {
            throw new InvalidArgumentException('Solo una nómina en borrador puede marcarse como pagada.');
        }

        return DB::connection('tenant')->transaction(function () use ($run, $userId) {
            $lockedRun = PayrollRun::whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($lockedRun->status !== 'draft') {
                throw new InvalidArgumentException('La nómina ya fue procesada.');
            }

            $compensationIds = $lockedRun->items()
                ->with('lines')
                ->get()
                ->flatMap(fn (PayrollItem $item) => $item->lines->pluck('professional_compensation_id'))
                ->filter();

            ProfessionalCompensation::whereIn('id', $compensationIds)->update(['status' => 'paid']);
            $lockedRun->items()->update(['status' => 'paid']);
            $lockedRun->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by_user_id' => $userId,
            ]);

            return $lockedRun->load(['items.employee', 'items.lines']);
        });
    }

    private function fixedSalaryForPeriod(Employee $employee, Carbon $periodStart, Carbon $periodEnd): float
    {
        if ($employee->compensation_type !== 'fixed_salary' || $employee->monthly_salary <= 0) {
            return 0.0;
        }

        $activeStart = $employee->hire_date?->isAfter($periodStart)
            ? $employee->hire_date->copy()
            : $periodStart->copy();
        $activeDays = $activeStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1;

        return round($employee->monthly_salary * ($activeDays / $periodStart->daysInMonth), 2);
    }
}
