<?php

namespace App\Core\Controllers;

use App\Core\Models\ClinicalDiagnosis;
use App\Core\Models\ClinicalEncounter;
use App\Core\Models\ClinicalEvolution;
use App\Core\Models\ClinicalPrescription;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Services\ClinicalIntegrityService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ClinicalEncounterController extends Controller
{
    public function __construct(
        protected ClinicalIntegrityService $integrityService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a listing of encounters for a patient.
     */
    public function index(Request $request, ?string $patientId = null): Response
    {
        $patient = $patientId !== null
            ? Patient::with(['medicalHistory'])->findOrFail($patientId)
            : null;

        $encounters = ClinicalEncounter::query()
            ->with(['patient', 'professional', 'diagnoses', 'evolution', 'prescriptions', 'amendments.amendedBy'])
            ->when($patientId, fn ($query) => $query->where('patient_id', $patientId))
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();

                $query->where(function ($query) use ($search) {
                    $query->where('chief_complaint', 'like', "%{$search}%")
                        ->orWhereHas('patient', function ($patientQuery) use ($search) {
                            $patientQuery->where('record_number', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('professional_id'), fn ($query) => $query->where('professional_id', $request->string('professional_id')->value()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('encounter_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('encounter_date', '<=', $request->date('date_to')))
            ->orderBy('encounter_date', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Clinic/Encounters/Index', [
            'patient' => $patient,
            'encounters' => $encounters,
            'professionals' => Professional::query()
                ->where('is_active', true)
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']),
            'filters' => $request->only(['search', 'status', 'professional_id', 'date_from', 'date_to']),
            'summary' => [
                'total' => ClinicalEncounter::query()->count(),
                'draft' => ClinicalEncounter::query()->where('status', 'draft')->count(),
                'finalized' => ClinicalEncounter::query()->whereIn('status', ['finalized', 'amended'])->count(),
                'today' => ClinicalEncounter::query()->whereDate('encounter_date', today())->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new clinical encounter draft.
     */
    public function create(Request $request, string $patientId): Response
    {
        $patient = Patient::with(['medicalHistory'])->findOrFail($patientId);
        $professionals = Professional::where('is_active', true)->get();
        $appointmentId = $request->input('appointment_id');

        return Inertia::render('Clinic/Encounters/Create', [
            'patient' => $patient,
            'professionals' => $professionals,
            'appointmentId' => $appointmentId,
        ]);
    }

    /**
     * Store a newly created draft encounter.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid', 'exists:tenant.patients,id'],
            'professional_id' => ['required', 'uuid', 'exists:tenant.professionals,id'],
            'appointment_id' => ['nullable', 'uuid', 'exists:tenant.appointments,id'],
            'encounter_date' => ['required', 'date'],
            'chief_complaint' => ['nullable', 'string'],
            'physical_examination' => ['nullable', 'string'],
            'vital_signs' => ['nullable', 'array'],
            // SOAP evolution
            'subjective' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'plan' => ['nullable', 'string'],
            'treatment_performed' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            // Diagnoses
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*.code' => ['nullable', 'string'],
            'diagnoses.*.description' => ['required_with:diagnoses', 'string'],
            'diagnoses.*.type' => ['required_with:diagnoses', 'string', 'in:presumptive,definitive'],
            // Prescriptions
            'prescriptions' => ['nullable', 'array'],
            'prescriptions.*.medication_name' => ['required_with:prescriptions', 'string'],
            'prescriptions.*.dosage' => ['required_with:prescriptions', 'string'],
            'prescriptions.*.frequency' => ['required_with:prescriptions', 'string'],
            'prescriptions.*.duration' => ['required_with:prescriptions', 'string'],
            'prescriptions.*.instructions' => ['nullable', 'string'],
        ]);

        /** @var ClinicalEncounter $encounter */
        $encounter = DB::connection('tenant')->transaction(function () use ($validated) {
            $encounter = ClinicalEncounter::create([
                'patient_id' => $validated['patient_id'],
                'professional_id' => $validated['professional_id'],
                'appointment_id' => $validated['appointment_id'] ?? null,
                'encounter_date' => Carbon::parse($validated['encounter_date']),
                'chief_complaint' => $validated['chief_complaint'] ?? null,
                'physical_examination' => $validated['physical_examination'] ?? null,
                'vital_signs' => $validated['vital_signs'] ?? null,
                'status' => 'draft',
            ]);

            ClinicalEvolution::create([
                'encounter_id' => $encounter->id,
                'subjective' => $validated['subjective'] ?? null,
                'objective' => $validated['objective'] ?? null,
                'assessment' => $validated['assessment'] ?? null,
                'plan' => $validated['plan'] ?? null,
                'treatment_performed' => $validated['treatment_performed'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
            ]);

            if (! empty($validated['diagnoses'])) {
                foreach ($validated['diagnoses'] as $diag) {
                    ClinicalDiagnosis::create([
                        'encounter_id' => $encounter->id,
                        'code' => $diag['code'] ?? null,
                        'description' => $diag['description'],
                        'type' => $diag['type'] ?? 'definitive',
                    ]);
                }
            }

            if (! empty($validated['prescriptions'])) {
                foreach ($validated['prescriptions'] as $rx) {
                    ClinicalPrescription::create([
                        'encounter_id' => $encounter->id,
                        'medication_name' => $rx['medication_name'],
                        'dosage' => $rx['dosage'],
                        'frequency' => $rx['frequency'],
                        'duration' => $rx['duration'],
                        'instructions' => $rx['instructions'] ?? null,
                    ]);
                }
            }

            return $encounter;
        });

        $this->auditLogger->logTenant('encounter.created', 'ClinicalEncounter', $encounter->id, [
            'patient_id' => $encounter->patient_id,
            'status' => 'draft',
        ]);

        return redirect()->route('clinic.encounters.show', $encounter->id)
            ->with('success', 'Borrador de encuentro clínico guardado con éxito.');
    }

    /**
     * Display the clinical encounter details.
     */
    public function show(string $id): Response
    {
        $encounter = ClinicalEncounter::with([
            'patient.medicalHistory',
            'professional',
            'evolution',
            'diagnoses',
            'prescriptions',
            'amendments.amendedBy',
            'finalizedBy',
        ])->findOrFail($id);

        return Inertia::render('Clinic/Encounters/Show', [
            'encounter' => $encounter,
        ]);
    }

    /**
     * Update a draft clinical encounter. Rejects if finalized.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $encounter = ClinicalEncounter::findOrFail($id);

        if ($encounter->status === 'finalized' || $encounter->status === 'amended') {
            return redirect()->back()->withErrors([
                'error' => 'No se puede modificar directamente una historia clínica finalizada. Debe registrar una enmienda.',
            ]);
        }

        $validated = $request->validate([
            'chief_complaint' => ['nullable', 'string'],
            'physical_examination' => ['nullable', 'string'],
            'vital_signs' => ['nullable', 'array'],
            'subjective' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'assessment' => ['nullable', 'string'],
            'plan' => ['nullable', 'string'],
            'treatment_performed' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
        ]);

        DB::connection('tenant')->transaction(function () use ($encounter, $validated) {
            $encounter->update([
                'chief_complaint' => $validated['chief_complaint'] ?? null,
                'physical_examination' => $validated['physical_examination'] ?? null,
                'vital_signs' => $validated['vital_signs'] ?? null,
            ]);

            $encounter->evolution()->updateOrCreate(
                ['encounter_id' => $encounter->id],
                [
                    'subjective' => $validated['subjective'] ?? null,
                    'objective' => $validated['objective'] ?? null,
                    'assessment' => $validated['assessment'] ?? null,
                    'plan' => $validated['plan'] ?? null,
                    'treatment_performed' => $validated['treatment_performed'] ?? null,
                    'recommendations' => $validated['recommendations'] ?? null,
                ]
            );
        });

        $this->auditLogger->logTenant('encounter.updated', 'ClinicalEncounter', $encounter->id, [
            'status' => 'draft',
        ]);

        return redirect()->back()->with('success', 'Borrador actualizado con éxito.');
    }

    /**
     * Finalize and seal the clinical encounter inmutably.
     */
    public function finalize(Request $request, string $id): RedirectResponse
    {
        $encounter = ClinicalEncounter::findOrFail($id);

        try {
            $userId = (string) Auth::guard('web')->id();
            $this->integrityService->finalize($encounter, $userId);
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        $this->auditLogger->logTenant('encounter.finalized', 'ClinicalEncounter', $encounter->id, [
            'integrity_hash' => $encounter->integrity_hash,
            'finalized_by' => $userId,
        ]);

        return redirect()->back()->with('success', 'Encuentro clínico finalizado y sellado inmutablemente.');
    }

    /**
     * Record a signed medical amendment for a finalized encounter.
     */
    public function amend(Request $request, string $id): RedirectResponse
    {
        $encounter = ClinicalEncounter::findOrFail($id);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'amended_content' => ['required', 'array'],
        ]);

        try {
            $userId = (string) Auth::guard('web')->id();
            $amendment = $this->integrityService->createAmendment(
                $encounter,
                $userId,
                $validated['reason'],
                $validated['amended_content']
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['reason' => $e->getMessage()]);
        }

        $this->auditLogger->logTenant('encounter.amended', 'ClinicalAmendment', $amendment->id, [
            'encounter_id' => $encounter->id,
            'reason' => $validated['reason'],
        ]);

        return redirect()->back()->with('success', 'Enmienda clínica registrada exitosamente con firma digital.');
    }
}
