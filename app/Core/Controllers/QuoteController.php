<?php

namespace App\Core\Controllers;

use App\Core\Models\Patient;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Professional;
use App\Core\Models\Quote;
use App\Core\Models\QuoteItem;
use App\Core\Services\QuoteCalculationService;
use App\Core\Services\TreatmentPlanGeneratorService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(
        protected QuoteCalculationService $calculationService,
        protected TreatmentPlanGeneratorService $planGenerator,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display quotes for a patient.
     */
    public function index(string $patientId): Response
    {
        $patient = Patient::findOrFail($patientId);
        $quotes = Quote::with(['professional', 'items.procedure'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Clinic/Quotes/Index', [
            'patient' => $patient,
            'quotes' => $quotes,
        ]);
    }

    /**
     * Show form for creating a new quote.
     */
    public function create(string $patientId): Response
    {
        $patient = Patient::findOrFail($patientId);
        $professionals = Professional::where('is_active', true)->get();
        $categories = ProcedureCategory::with('procedures')->get();
        $suggestedNumber = $this->calculationService->generateQuoteNumber();

        return Inertia::render('Clinic/Quotes/Create', [
            'patient' => $patient,
            'professionals' => $professionals,
            'categories' => $categories,
            'suggestedNumber' => $suggestedNumber,
        ]);
    }

    /**
     * Store a newly created quote.
     */
    public function store(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate([
            'professional_id' => ['nullable', 'uuid', 'exists:tenant.professionals,id'],
            'alternative_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['required', 'uuid', 'exists:tenant.procedures,id'],
            'items.*.tooth_number' => ['nullable', 'integer', Rule::in($this->validFdiToothNumbers())],
            'items.*.surface' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        /** @var Quote $quote */
        $quote = DB::connection('tenant')->transaction(function () use ($patient, $validated) {
            $quoteNumber = $this->calculationService->generateQuoteNumber();

            $quote = Quote::create([
                'patient_id' => $patient->id,
                'professional_id' => $validated['professional_id'] ?? null,
                'quote_number' => $quoteNumber,
                'version' => 1,
                'alternative_name' => $validated['alternative_name'],
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by_user_id' => Auth::guard('web')->id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $procedure = Procedure::findOrFail($itemData['procedure_id']);

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'procedure_id' => $procedure->id,
                    'tooth_number' => $itemData['tooth_number'] ?? null,
                    'surface' => $itemData['surface'] ?? 'all',
                    'unit_price' => $procedure->price,
                    'quantity' => $itemData['quantity'],
                    'discount_percentage' => $itemData['discount_percentage'] ?? 0.00,
                    'subtotal' => 0.00,
                    'tax' => $procedure->tax_rate,
                    'total' => 0.00,
                    'is_approved' => true,
                ]);
            }

            return $this->calculationService->recalculate($quote);
        });

        $this->auditLogger->logTenant('quote.created', 'Quote', $quote->id, [
            'patient_id' => $patient->id,
            'quote_number' => $quote->quote_number,
            'grand_total' => $quote->grand_total,
        ]);

        return redirect()->route('clinic.quotes.show', $quote->id)
            ->with('success', 'Presupuesto creado con éxito.');
    }

    /**
     * Display the specified quote.
     */
    public function show(string $id): Response
    {
        $quote = Quote::with(['patient.medicalHistory', 'professional', 'items.procedure', 'treatmentPlan'])->findOrFail($id);

        return Inertia::render('Clinic/Quotes/Show', [
            'quote' => $quote,
        ]);
    }

    /**
     * Approve quote and automatically generate active Treatment Plan.
     */
    public function approve(Request $request, string $id): RedirectResponse
    {
        $quote = Quote::with(['patient', 'treatmentPlan'])->findOrFail($id);

        $validated = $request->validate([
            'approved_by_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($quote->treatmentPlan) {
            return redirect()->route('clinic.treatment_plans.show', $quote->treatmentPlan->id)
                ->with('info', 'Este presupuesto ya tiene un plan de tratamiento generado.');
        }

        if (! in_array($quote->status, ['draft', 'presented', 'partially_approved'], true)) {
            return redirect()->back()->with('error', 'El estado actual del presupuesto no permite aprobarlo.');
        }

        $userId = (string) Auth::guard('web')->id();

        $quote->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_name' => $validated['approved_by_name'] ?? $quote->patient->full_name,
        ]);

        $plan = $this->planGenerator->generateFromQuote($quote, $userId);

        $this->auditLogger->logTenant('quote.approved', 'Quote', $quote->id, [
            'plan_id' => $plan->id,
            'approved_by' => $quote->approved_by_name,
        ]);

        return redirect()->route('clinic.treatment_plans.show', $plan->id)
            ->with('success', 'Presupuesto aprobado y Plan de Tratamiento generado exitosamente.');
    }

    /**
     * Reject quote.
     */
    public function reject(Request $request, string $id): RedirectResponse
    {
        $quote = Quote::with('treatmentPlan')->findOrFail($id);

        if ($quote->treatmentPlan || ! in_array($quote->status, ['draft', 'presented'], true)) {
            return redirect()->back()->with('error', 'Este presupuesto ya no puede rechazarse.');
        }

        $quote->update([
            'status' => 'rejected',
        ]);

        $this->auditLogger->logTenant('quote.rejected', 'Quote', $quote->id, [
            'quote_number' => $quote->quote_number,
        ]);

        return redirect()->back()->with('success', 'Presupuesto marcado como rechazado.');
    }

    /**
     * Valid permanent and primary tooth numbers in FDI notation.
     *
     * @return list<int>
     */
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
