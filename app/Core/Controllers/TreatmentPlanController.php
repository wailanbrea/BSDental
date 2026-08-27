<?php

namespace App\Core\Controllers;

use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
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
        $plan = TreatmentPlan::with(['patient', 'quote', 'items.procedure', 'items.appointment', 'items.professional'])->findOrFail($id);

        return Inertia::render('Clinic/TreatmentPlans/Show', [
            'plan' => $plan,
            'professionals' => Professional::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get(),
        ]);
    }

    /**
     * Mark a treatment item as completed.
     */
    public function completeItem(Request $request, string $itemId): RedirectResponse
    {
        $validated = $request->validate([
            'professional_id' => ['required', 'uuid', 'exists:tenant.professionals,id'],
        ]);
        $item = TreatmentPlanItem::with('treatmentPlan')->findOrFail($itemId);
        $professional = Professional::where('is_active', true)->findOrFail($validated['professional_id']);
        $userId = (string) Auth::guard('web')->id();

        $completed = $this->planService->completeItem($item, $userId, null, $professional);

        $this->auditLogger->logTenant('treatment_item.completed', 'TreatmentPlanItem', $completed->id, [
            'plan_id' => $completed->treatment_plan_id,
            'procedure_id' => $completed->procedure_id,
            'professional_id' => $professional->id,
        ]);

        return redirect()->back()->with('success', 'Procedimiento marcado como realizado.');
    }
}
