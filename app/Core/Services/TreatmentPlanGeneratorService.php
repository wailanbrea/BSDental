<?php

namespace App\Core\Services;

use App\Core\Models\Quote;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TreatmentPlanGeneratorService
{
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
     * Complete a treatment plan item idempotently and update plan progress metrics.
     */
    public function completeItem(
        TreatmentPlanItem $item,
        string $userId,
        ?string $encounterId = null
    ): TreatmentPlanItem {
        if ($item->status === 'completed') {
            return $item; // Idempotent
        }

        DB::connection('tenant')->transaction(function () use ($item, $userId, $encounterId) {
            $item->update([
                'status' => 'completed',
                'encounter_id' => $encounterId,
                'completed_at' => now(),
                'completed_by_user_id' => $userId,
            ]);

            $this->recalculateProgress($item->treatmentPlan);
        });

        return $item;
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
