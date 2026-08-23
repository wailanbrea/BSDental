<?php

namespace App\Core\Controllers;

use App\Core\Models\Patient;
use App\Core\Models\PatientMedicalHistory;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Professional;
use App\Core\Models\Quote;
use App\Core\Models\QuoteItem;
use App\Core\Services\PatientDuplicateDetector;
use App\Core\Services\PatientRecordNumberGenerator;
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
        protected AuditLogger $auditLogger,
        protected PatientRecordNumberGenerator $recordNumberGenerator,
        protected PatientDuplicateDetector $duplicateDetector,
    ) {}

    /** Display the global quote center, including prospect quotes. */
    public function allIndex(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'presented', 'approved', 'partially_approved', 'rejected', 'converted'])],
            'type' => ['nullable', Rule::in(['patient', 'prospect'])],
        ]);

        $quotes = Quote::query()
            ->with(['patient', 'professional', 'items'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('quote_number', 'like', "%{$search}%")
                        ->orWhere('alternative_name', 'like', "%{$search}%")
                        ->orWhere('prospect_first_name', 'like', "%{$search}%")
                        ->orWhere('prospect_last_name', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($patient) => $patient->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('record_number', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when(($filters['type'] ?? null) === 'prospect', fn ($query) => $query->whereNull('patient_id'))
            ->when(($filters['type'] ?? null) === 'patient', fn ($query) => $query->whereNotNull('patient_id'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Clinic/Quotes/AllIndex', [
            'quotes' => $quotes,
            'filters' => $filters,
        ]);
    }

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
            'mode' => 'patient',
            'professionals' => $professionals,
            'categories' => $categories,
            'suggestedNumber' => $suggestedNumber,
        ]);
    }

    /** Show the quick quote form for a person who is not yet a patient. */
    public function quickCreate(): Response
    {
        return Inertia::render('Clinic/Quotes/Create', [
            'patient' => null,
            'mode' => 'prospect',
            'professionals' => Professional::where('is_active', true)->get(),
            'categories' => ProcedureCategory::with('procedures')->get(),
            'suggestedNumber' => $this->calculationService->generateQuoteNumber(),
        ]);
    }

    /**
     * Store a newly created quote.
     */
    public function store(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate($this->quoteRules());
        $quote = $this->createQuote($patient, $validated);

        $this->auditLogger->logTenant('quote.created', 'Quote', $quote->id, [
            'patient_id' => $patient->id,
            'quote_number' => $quote->quote_number,
            'grand_total' => $quote->grand_total,
        ]);

        return redirect()->route('clinic.quotes.show', $quote->id)
            ->with('success', 'Presupuesto creado con éxito.');
    }

    /** Store a quote for a prospect without creating a clinical record. */
    public function storeQuick(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge($this->quoteRules(), [
            'prospect_first_name' => ['required', 'string', 'max:100'],
            'prospect_last_name' => ['required', 'string', 'max:100'],
            'prospect_phone' => ['nullable', 'required_without:prospect_email', 'string', 'max:50'],
            'prospect_email' => ['nullable', 'required_without:prospect_phone', 'email', 'max:255'],
        ]));

        $quote = $this->createQuote(null, $validated);

        $this->auditLogger->logTenant('quote.quick_created', 'Quote', $quote->id, [
            'quote_number' => $quote->quote_number,
            'grand_total' => $quote->grand_total,
        ]);

        return redirect()->route('clinic.quotes.show', $quote->id)
            ->with('success', 'Cotización rápida creada. Vincúlala a un paciente antes de aprobarla.');
    }

    /**
     * Display the specified quote.
     */
    public function show(string $id): Response
    {
        $quote = Quote::with(['patient.medicalHistory', 'professional', 'items.procedure', 'treatmentPlan'])->findOrFail($id);

        return Inertia::render('Clinic/Quotes/Show', [
            'quote' => $quote,
            'prospectCandidates' => $quote->patient_id ? [] : $this->duplicateDetector
                ->findCandidates(null, $quote->prospect_phone, $quote->prospect_first_name, $quote->prospect_last_name)
                ->map(fn (Patient $patient) => [
                    'id' => $patient->id,
                    'record_number' => $patient->record_number,
                    'full_name' => $patient->full_name,
                    'phone' => $patient->phone,
                    'email' => $patient->email,
                ])->values(),
        ]);
    }

    /** Link a prospect quote to an existing patient or create their clinical record. */
    public function convertToPatient(Request $request, string $id): RedirectResponse
    {
        $quote = Quote::findOrFail($id);

        if ($quote->patient_id) {
            return redirect()->route('clinic.quotes.show', $quote->id)
                ->with('info', 'Este presupuesto ya está vinculado a un paciente.');
        }

        $existing = $request->validate([
            'existing_patient_id' => ['nullable', 'uuid', 'exists:tenant.patients,id'],
        ]);

        if ($existing['existing_patient_id'] ?? null) {
            $quote->update(['patient_id' => $existing['existing_patient_id']]);
            $this->auditLogger->logTenant('quote.prospect_linked', 'Quote', $quote->id, [
                'patient_id' => $quote->patient_id,
            ]);

            return redirect()->route('clinic.quotes.show', $quote->id)
                ->with('success', 'Cotización vinculada al paciente existente.');
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'identification_type' => ['nullable', 'string', 'max:50'],
            'identification_number' => ['nullable', 'string', 'max:100'],
        ]);

        $candidates = $this->duplicateDetector->findCandidates(
            $validated['identification_number'] ?? null,
            $validated['phone'] ?? null,
            $validated['first_name'],
            $validated['last_name'],
        );

        if ($candidates->isNotEmpty()) {
            return redirect()->back()->with('error', 'Encontramos un paciente similar. Revísalo y vincúlalo para evitar duplicados.');
        }

        /** @var Patient $patient */
        $patient = DB::connection('tenant')->transaction(function () use ($quote, $validated) {
            $patient = Patient::create([
                'record_number' => $this->recordNumberGenerator->generate(),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'identification_type' => $validated['identification_type'] ?? null,
                'identification_number' => $validated['identification_number'] ?? null,
                'source' => 'quick_quote',
                'status' => 'active',
            ]);

            PatientMedicalHistory::create(['patient_id' => $patient->id]);
            $quote->update(['patient_id' => $patient->id]);

            return $patient;
        });

        $this->auditLogger->logTenant('patient.created', 'Patient', $patient->id, ['source' => 'quick_quote']);
        $this->auditLogger->logTenant('quote.prospect_converted', 'Quote', $quote->id, ['patient_id' => $patient->id]);

        return redirect()->route('clinic.quotes.show', $quote->id)
            ->with('success', "Paciente {$patient->record_number} creado y cotización vinculada.");
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

        if (! $quote->patient_id || ! $quote->patient) {
            return redirect()->back()->with('error', 'Convierte o vincula el prospecto a un paciente antes de aprobar el presupuesto.');
        }

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

    /** @return array<string, mixed> */
    private function quoteRules(): array
    {
        return [
            'professional_id' => ['nullable', 'uuid', 'exists:tenant.professionals,id'],
            'alternative_name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.procedure_id' => ['required', 'uuid', 'exists:tenant.procedures,id'],
            'items.*.tooth_number' => ['nullable', 'integer', Rule::in($this->validFdiToothNumbers())],
            'items.*.surface' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function createQuote(?Patient $patient, array $validated): Quote
    {
        return DB::connection('tenant')->transaction(function () use ($patient, $validated) {
            $quote = Quote::create([
                'patient_id' => $patient?->id,
                'prospect_first_name' => $patient ? null : $validated['prospect_first_name'],
                'prospect_last_name' => $patient ? null : $validated['prospect_last_name'],
                'prospect_phone' => $patient ? null : ($validated['prospect_phone'] ?? null),
                'prospect_email' => $patient ? null : ($validated['prospect_email'] ?? null),
                'professional_id' => $validated['professional_id'] ?? null,
                'quote_number' => $this->calculationService->generateQuoteNumber(),
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
    }
}
