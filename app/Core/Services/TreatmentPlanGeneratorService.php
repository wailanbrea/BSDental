<?php

namespace App\Core\Services;

use App\Core\Models\ClinicalEncounter;
use App\Core\Models\Professional;
use App\Core\Models\Quote;
use App\Core\Models\TreatmentExecution;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TreatmentPlanGeneratorService
{
    public function __construct(
        protected PayrollService $payrollService,
        protected InventoryStockService $inventoryStockService,
        protected FollowUpTaskService $followUpTaskService,
    ) {}

    /**
     * Convert an approved quote into an active treatment plan.
     */
    public function generateFromQuote(Quote $quote, ?string $userId = null): TreatmentPlan
    {
        // Check if plan already exists for this quote to prevent duplicate generation
        $existingPlan = TreatmentPlan::where('quote_id', $quote->id)->first();
        if ($existingPlan) {
            return $existingPlan;
        }

        if ($quote->status !== 'approved' && $quote->status !== 'partially_approved') {
            throw new InvalidArgumentException('Solo se pueden generar planes de tratamiento a partir de presupuestos aprobados.');
        }

        return DB::connection('tenant')->transaction(function () use ($quote, $userId) {
            $approvedItems = $quote->items()->where('is_approved', true)->get();

            $plan = TreatmentPlan::create([
                'patient_id' => $quote->patient_id,
                'quote_id' => $quote->id,
                'title' => "Plan: {$quote->alternative_name} ({$quote->quote_number})",
                'status' => 'active',
                'total_estimated' => $quote->grand_total,
                'total_performed' => 0.00,
                'progress_percentage' => 0.00,
                'created_by_user_id' => $userId,
            ]);

            foreach ($approvedItems as $idx => $item) {
                TreatmentPlanItem::create([
                    'treatment_plan_id' => $plan->id,
                    'quote_item_id' => $item->id,
                    'clinical_plan_item_id' => $item->clinical_plan_item_id,
                    'procedure_id' => $item->procedure_id,
                    'tooth_number' => $item->tooth_number,
                    'surface' => $item->surface,
                    'phase' => $item->phase ?? 1,
                    'price' => $item->total,
                    'status' => 'pending',
                ]);
            }

            $quote->update(['status' => 'converted']);

            return $plan;
        });
    }

    /**
     * Complete an item only when it is supported by a patient-matched clinical encounter.
     */
    public function completeItem(
        TreatmentPlanItem $item,
        string $userId,
        ClinicalEncounter $encounter,
        Professional $professional,
        ?Warehouse $warehouse = null,
    ): TreatmentPlanItem {
        if ($item->status === 'completed') {
            return $item; // Idempotent
        }

        DB::connection('tenant')->transaction(function () use ($item, $userId, $encounter, $professional, $warehouse) {
            $lockedItem = TreatmentPlanItem::with(['treatmentPlan.patient', 'procedure'])
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedItem->status === 'completed') {
                return;
            }

            if ($encounter->patient_id !== $lockedItem->treatmentPlan->patient_id) {
                throw new InvalidArgumentException('El encuentro clínico no pertenece al paciente del tratamiento.');
            }

            if ($encounter->professional_id !== $professional->id) {
                throw new InvalidArgumentException('El profesional ejecutor debe coincidir con el profesional del encuentro clínico.');
            }

            $hasMaterials = $lockedItem->procedure->materialRules()->exists();
            if ($hasMaterials && $warehouse === null) {
                throw new InvalidArgumentException('Seleccione una bodega para consumir los materiales requeridos.');
            }

            $execution = TreatmentExecution::create([
                'treatment_plan_item_id' => $lockedItem->id,
                'clinical_encounter_id' => $encounter->id,
                'professional_id' => $professional->id,
                'executed_by_user_id' => $userId,
                'executed_at' => now(),
            ]);

            if ($warehouse !== null) {
                $this->inventoryStockService->consumeMaterialsForProcedure($lockedItem, $warehouse, $execution, $userId);
            }

            $lockedItem->update([
                'status' => 'completed',
                'encounter_id' => $encounter->id,
                'professional_id' => $professional->id,
                'completed_at' => now(),
                'completed_by_user_id' => $userId,
            ]);

            $this->payrollService->accrueProcedureCommission($lockedItem->fresh(), $professional);

            $this->recalculateProgress($lockedItem->treatmentPlan);

            $patient = $lockedItem->treatmentPlan->patient;
            $this->followUpTaskService->createTask(
                $patient,
                'post_op',
                "Control post-operatorio: {$lockedItem->procedure->name}",
                now()->addDays(2),
                'medium',
                $encounter->appointment_id,
                $userId,
                "Generada tras la ejecución {$execution->id}.",
                $execution,
            );

            if ($lockedItem->treatmentPlan->fresh()->status !== 'completed') {
                $this->followUpTaskService->createTask(
                    $patient,
                    'treatment_incomplete',
                    "Continuar tratamiento: {$lockedItem->treatmentPlan->title}",
                    now()->addDays(7),
                    'medium',
                    $encounter->appointment_id,
                    $userId,
                    "Plan pendiente tras la ejecución {$execution->id}.",
                    $execution,
                );
            }
        });

        return $item->refresh();
    }

    /**
     * Recalculate treatment plan progress metrics.
     */
    public function recalculateProgress(TreatmentPlan $plan): TreatmentPlan
    {
        $items = $plan->items()->get();
        $totalItems = $items->count();

        if ($totalItems === 0) {
            return $plan;
        }

        $completedItems = $items->where('status', 'completed');
        $totalEstimated = (float) $items->sum('price');
        $totalPerformed = (float) $completedItems->sum('price');

        $progressPercentage = round(($completedItems->count() / $totalItems) * 100, 2);

        $planStatus = $progressPercentage >= 100.0 ? 'completed' : 'active';

        $plan->update([
            'total_estimated' => $totalEstimated,
            'total_performed' => $totalPerformed,
            'progress_percentage' => $progressPercentage,
            'status' => $planStatus,
        ]);

        return $plan;
    }
}
