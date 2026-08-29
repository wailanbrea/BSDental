<?php

namespace App\Core\Controllers;

use App\Core\Models\ClinicalDiagnosis;
use App\Core\Models\ClinicalEncounter;
use App\Core\Models\ClinicalPlan;
use App\Core\Models\ClinicalPlanItem;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Quote;
use App\Core\Models\QuoteItem;
use App\Core\Services\QuoteCalculationService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClinicalPlanController extends Controller
{
    public function __construct(
        protected QuoteCalculationService $calculationService,
        protected AuditLogger $auditLogger,
    ) {}

    public function create(string $encounterId): Response
    {
        $encounter = ClinicalEncounter::with(['patient', 'diagnoses'])->findOrFail($encounterId);

        return Inertia::render('Clinic/ClinicalPlans/Create', [
            'encounter' => $encounter,
            'categories' => ProcedureCategory::with(['procedures' => fn ($query) => $query->where('is_active', true)])->get(),
        ]);
    }

    public function store(Request $request, string $encounterId): RedirectResponse
    {
        $encounter = ClinicalEncounter::findOrFail($encounterId);
        $validated = $request->validate($this->planRules());

        if (! empty($validated['clinical_diagnosis_id'])) {
            ClinicalDiagnosis::whereKey($validated['clinical_diagnosis_id'])
                ->where('encounter_id', $encounter->id)
                ->firstOrFail();
        }

        /** @var ClinicalPlan $plan */
        $plan = DB::connection('tenant')->transaction(function () use ($encounter, $validated) {
            $plan = ClinicalPlan::create([
                'patient_id' => $encounter->patient_id,
                'clinical_encounter_id' => $encounter->id,
                'clinical_diagnosis_id' => $validated['clinical_diagnosis_id'] ?? null,
                'title' => $validated['title'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by_user_id' => Auth::guard('web')->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $procedure = Procedure::whereKey($item['procedure_id'])->where('is_active', true)->firstOrFail();

                ClinicalPlanItem::create([
                    'clinical_plan_id' => $plan->id,
                    'procedure_id' => $procedure->id,
                    'tooth_number' => $item['tooth_number'] ?? null,
                    'surface' => $item['surface'] ?? 'all',
                    'quantity' => $item['quantity'],
                    'clinical_note' => $item['clinical_note'] ?? null,
                    'priority' => $item['priority'] ?? 'normal',
                    'estimated_minutes' => $item['estimated_minutes'] ?? $procedure->estimated_minutes,
                    'status' => $item['status'] ?? 'proposed',
                ]);
            }

            return $plan;
        });

        $this->auditLogger->logTenant('clinical_plan.created', 'ClinicalPlan', $plan->id, [
            'encounter_id' => $encounter->id,
            'diagnosis_id' => $plan->clinical_diagnosis_id,
            'item_count' => count($validated['items']),
        ]);

        return redirect()->route('clinic.clinical_plans.show', $plan->id)
            ->with('success', 'Plan clínico creado. Puedes cotizar solo los procedimientos seleccionados.');
    }

    public function show(string $id): Response
    {
        $plan = ClinicalPlan::with([
            'patient',
            'encounter.professional',
            'diagnosis',
            'items.procedure',
            'items.quoteItems.quote',
        ])->findOrFail($id);

        return Inertia::render('Clinic/ClinicalPlans/Show', ['plan' => $plan]);
    }

    public function convertToQuote(Request $request, string $id): RedirectResponse
    {
        $plan = ClinicalPlan::with('encounter')->findOrFail($id);
        $validated = $request->validate([
            'alternative_name' => ['required', 'string', 'max:255'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        /** @var Quote $quote */
        $quote = DB::connection('tenant')->transaction(function () use ($plan, $validated) {
            $items = ClinicalPlanItem::where('clinical_plan_id', $plan->id)
                ->whereIn('id', $validated['item_ids'])
                ->lockForUpdate()
                ->get();

            abort_unless($items->count() === count($validated['item_ids']), 404);
            abort_unless($items->every(fn (ClinicalPlanItem $item) => in_array($item->status, ['proposed', 'deferred'], true)), 422);

            $quote = Quote::create([
                'patient_id' => $plan->patient_id,
                'professional_id' => $plan->encounter->professional_id,
                'quote_number' => $this->calculationService->generateQuoteNumber(),
                'version' => 1,
                'alternative_name' => $validated['alternative_name'],
                'status' => 'draft',
                'notes' => $plan->notes,
                'created_by_user_id' => Auth::guard('web')->id(),
            ]);

            foreach ($items as $item) {
                $procedure = Procedure::findOrFail($item->procedure_id);

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'clinical_plan_item_id' => $item->id,
                    'procedure_id' => $procedure->id,
                    'tooth_number' => $item->tooth_number,
                    'surface' => $item->surface,
                    'phase' => 1,
                    'unit_price' => $procedure->price,
                    'quantity' => $item->quantity,
                    'discount_percentage' => 0.00,
                    'subtotal' => 0.00,
                    'tax' => $procedure->tax_rate,
                    'total' => 0.00,
                    'is_approved' => true,
                ]);
            }

            $items->each->update(['status' => 'quoted']);
            $plan->update([
                'status' => $plan->items()->whereNotIn('status', ['quoted'])->exists() ? 'partially_quoted' : 'quoted',
            ]);

            return $this->calculationService->recalculate($quote);
        });

        $this->auditLogger->logTenant('clinical_plan.converted_to_quote', 'ClinicalPlan', $plan->id, [
            'quote_id' => $quote->id,
            'item_count' => count($validated['item_ids']),
        ]);
        $this->auditLogger->logTenant('quote.created_from_clinical_plan', 'Quote', $quote->id, [
            'clinical_plan_id' => $plan->id,
        ]);

        return redirect()->route('clinic.quotes.show', $quote->id)
            ->with('success', 'Cotización creada con precios e impuestos fijados desde el catálogo vigente.');
    }

    /** @return array<string, mixed> */
    private function planRules(): array
    {
        return [
            'clinical_diagnosis_id' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['required', 'uuid', 'exists:tenant.procedures,id'],
            'items.*.tooth_number' => ['nullable', 'integer', Rule::in($this->validFdiToothNumbers())],
            'items.*.surface' => ['nullable', 'in:all,vestibular,lingual_palatal,mesial,distal,occlusal_incisal'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.clinical_note' => ['nullable', 'string', 'max:2000'],
            'items.*.priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'items.*.estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'items.*.status' => ['nullable', Rule::in(['proposed', 'deferred'])],
        ];
    }

    /** @return list<int> */
    private function validFdiToothNumbers(): array
    {
        $teeth = [];

        foreach ([1, 2, 3, 4] as $quadrant) {
            foreach (range(1, 8) as $position) {
                $teeth[] = ($quadrant * 10) + $position;
            }
        }

        foreach ([5, 6, 7, 8] as $quadrant) {
            foreach (range(1, 5) as $position) {
                $teeth[] = ($quadrant * 10) + $position;
            }
        }

        return $teeth;
    }
}
