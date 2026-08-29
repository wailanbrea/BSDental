<?php

namespace App\Core\Controllers;

use App\Core\Models\ClinicalEncounter;
use App\Core\Models\DentalLaboratory;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Models\TreatmentExecution;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\Warehouse;
use App\Core\Services\TreatmentPlanGeneratorService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TreatmentPlanController extends Controller
{
    public function __construct(
        protected TreatmentPlanGeneratorService $planService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display treatment plans for a patient.
     */
    public function index(string $patientId): Response
    {
        $patient = Patient::findOrFail($patientId);
        $plans = TreatmentPlan::with(['items.procedure'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Clinic/TreatmentPlans/Index', [
            'patient' => $patient,
            'plans' => $plans,
        ]);
    }

    /**
     * Display treatment plan details and execution checklist.
     */
    public function show(string $id): Response
    {
        $plan = TreatmentPlan::with(['patient', 'quote', 'items.procedure', 'items.appointment', 'items.professional', 'items.encounter', 'items.execution'])->findOrFail($id);

        return Inertia::render('Clinic/TreatmentPlans/Show', [
            'plan' => $plan,
            'professionals' => Professional::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get(),
            'encounters' => ClinicalEncounter::where('patient_id', $plan->patient_id)->orderByDesc('encounter_date')->get(['id', 'professional_id', 'encounter_date', 'status']),
            'warehouses' => Warehouse::orderByDesc('is_main')->orderBy('name')->get(['id', 'name']),
            'laboratories' => DentalLaboratory::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Mark a treatment item as completed.
     */
    public function completeItem(Request $request, string $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'professional_id' => ['required', 'uuid', 'exists:tenant.professionals,id'],
            'encounter_id' => ['required', 'uuid', 'exists:tenant.clinical_encounters,id'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:tenant.warehouses,id'],
        ]);
        $item = TreatmentPlanItem::with('treatmentPlan')->findOrFail($itemId);
        $professional = Professional::where('is_active', true)->findOrFail($validated['professional_id']);
        $encounter = ClinicalEncounter::findOrFail($validated['encounter_id']);
        $warehouse = isset($validated['warehouse_id']) ? Warehouse::findOrFail($validated['warehouse_id']) : null;
        $userId = (string) Auth::guard('web')->id();

        try {
            $completed = $this->planService->completeItem($item, $userId, $encounter, $professional, $warehouse);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['execution' => $e->getMessage()]);
        }

        $execution = TreatmentExecution::where('treatment_plan_item_id', $completed->id)->first();

        $this->auditLogger->logTenant('treatment_item.completed', 'TreatmentPlanItem', $completed->id, [
            'plan_id' => $completed->treatment_plan_id,
            'procedure_id' => $completed->procedure_id,
            'professional_id' => $professional->id,
            'encounter_id' => $encounter->id,
            'treatment_execution_id' => $execution?->id,
        ]);

        return redirect()->back()->with('success', 'Procedimiento marcado como realizado.');
    }
}
