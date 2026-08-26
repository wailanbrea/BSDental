<?php

namespace App\Core\Controllers;

use App\Core\Models\Odontogram;
use App\Core\Models\OdontogramEntry;
use App\Core\Models\Patient;
use App\Core\Models\PeriodontalExam;
use App\Core\Models\PeriodontalMeasurement;
use App\Core\Services\OdontogramService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OdontogramController extends Controller
{
    public function __construct(
        protected OdontogramService $odontogramService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display the odontogram for a patient.
     */
    public function show(string $patientId): Response
    {
        $patient = Patient::with('medicalHistory')->findOrFail($patientId);
        $odontogram = $this->odontogramService->getOrCreateForPatient($patientId);
        $matrix = $this->odontogramService->getToothMatrix($odontogram);

        $entries = OdontogramEntry::with(['recordedBy', 'encounter'])
            ->where('odontogram_id', $odontogram->id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        $periodontalExam = PeriodontalExam::with('measurements')
            ->where('odontogram_id', $odontogram->id)
            ->latest('recorded_at')
            ->first();

        $dentalFiles = $patient->files()
            ->whereIn('category', ['radiography', 'photo'])
            ->latest()
            ->get();

        return Inertia::render('Clinic/Odontogram/Show', [
            'patient' => $patient,
            'odontogram' => $odontogram,
            'matrix' => $matrix,
            'entries' => $entries,
            'periodontalExam' => $periodontalExam,
            'dentalFiles' => $dentalFiles,
        ]);
    }

    /**
     * Store a new tooth condition in the odontogram.
     */
    public function storeEntry(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);
        $odontogram = $this->odontogramService->getOrCreateForPatient($patientId);

        $validated = $request->validate([
            'tooth_number' => ['required', 'integer', Rule::in($this->validFdiToothNumbers())],
            'surface' => ['nullable', 'string', 'required_without:surfaces', 'in:all,vestibular,lingual_palatal,mesial,distal,occlusal_incisal'],
            'surfaces' => ['nullable', 'array', 'required_without:surface', 'min:1'],
            'surfaces.*' => ['string', 'distinct', 'in:all,vestibular,lingual_palatal,mesial,distal,occlusal_incisal'],
            'condition' => ['required', 'string', 'in:caries,restored_composite,restored_amalgam,crown,endodontic,missing,implant,prosthesis,sealant,fracture,healthy'],
            'entry_type' => ['nullable', 'string', 'in:finding,diagnosis,procedure,device,anatomical_state'],
            'code_system' => ['nullable', 'string', 'max:80'],
            'clinical_code' => ['nullable', 'string', 'max:80'],
            'clinical_display' => ['nullable', 'string', 'max:255'],
            'clinical_status' => ['nullable', 'string', 'in:active,inactive,resolved,recurrence'],
            'verification_status' => ['nullable', 'string', 'in:provisional,differential,confirmed,refuted,entered_in_error'],
            'lifecycle_state' => ['required', 'string', 'in:initial_diagnosis,planned,approved,completed'],
            'notes' => ['nullable', 'string', 'max:500'],
            'encounter_id' => ['nullable', 'uuid', 'exists:tenant.clinical_encounters,id'],
            'procedure_id' => ['nullable', 'uuid', 'exists:tenant.procedures,id'],
            'supersedes_entry_id' => ['nullable', 'uuid', 'exists:tenant.odontogram_entries,id'],
            'amendment_reason' => ['nullable', 'required_with:supersedes_entry_id', 'string', 'min:5', 'max:500'],
        ]);

        if (! empty($validated['supersedes_entry_id'])) {
            $superseded = OdontogramEntry::where('odontogram_id', $odontogram->id)
                ->where('tooth_number', $validated['tooth_number'])
                ->findOrFail($validated['supersedes_entry_id']);

            if ($superseded->corrections()->exists()) {
                return back()->withErrors(['supersedes_entry_id' => 'Este registro ya tiene una corrección posterior.']);
            }
        }

        $userId = Auth::guard('web')->id();

        $entry = $this->odontogramService->recordClinicalEntry(
            $odontogram,
            $validated,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('odontogram.entry_created', 'OdontogramEntry', $entry->id, [
            'patient_id' => $patient->id,
            'tooth_number' => $entry->tooth_number,
            'condition' => $entry->condition,
            'surface' => $entry->surface,
            'surfaces' => $entry->surfaces,
            'entry_type' => $entry->entry_type,
            'lifecycle_state' => $entry->lifecycle_state,
        ]);

        return redirect()->back()->with('success', "Pieza {$entry->tooth_number} actualizada ({$entry->condition}).");
    }

    public function storePeriodontalMeasurements(Request $request, string $patientId): RedirectResponse
    {
        Patient::findOrFail($patientId);
        $odontogram = $this->odontogramService->getOrCreateForPatient($patientId);
        $validated = $request->validate([
            'tooth_number' => ['required', 'integer', Rule::in($this->validFdiToothNumbers())],
            'measurements' => ['required', 'array', 'min:1', 'max:6'],
            'measurements.*.site' => ['required', 'distinct', 'in:mb,b,db,ml,l,dl'],
            'measurements.*.probing_depth' => ['nullable', 'integer', 'between:0,15'],
            'measurements.*.recession' => ['nullable', 'integer', 'between:-10,15'],
            'measurements.*.bleeding' => ['boolean'],
            'measurements.*.plaque' => ['boolean'],
            'measurements.*.suppuration' => ['boolean'],
            'measurements.*.mobility' => ['nullable', 'integer', 'between:0,3'],
            'measurements.*.furcation' => ['nullable', 'integer', 'between:0,3'],
            'measurements.*.is_implant' => ['boolean'],
        ]);

        $userId = Auth::guard('web')->id();
        $exam = PeriodontalExam::firstOrCreate(
            ['odontogram_id' => $odontogram->id, 'status' => 'draft'],
            ['recorded_by_user_id' => $userId, 'recorded_at' => now()]
        );

        foreach ($validated['measurements'] as $measurement) {
            PeriodontalMeasurement::updateOrCreate(
                ['periodontal_exam_id' => $exam->id, 'tooth_number' => $validated['tooth_number'], 'site' => $measurement['site']],
                $measurement
            );
        }

        $exam->update(['recorded_by_user_id' => $userId, 'recorded_at' => now()]);
        $this->auditLogger->logTenant('odontogram.periodontal_measurements_saved', 'PeriodontalExam', $exam->id, [
            'patient_id' => $patientId,
            'tooth_number' => $validated['tooth_number'],
        ]);

        return back()->with('success', "Sondaje periodontal de la pieza {$validated['tooth_number']} guardado.");
    }

    public function storeCariesRisk(Request $request, string $patientId): RedirectResponse
    {
        Patient::findOrFail($patientId);
        $odontogram = $this->odontogramService->getOrCreateForPatient($patientId);
        $validated = $request->validate([
            'caries_risk_level' => ['required', 'in:low,moderate,high,extreme'],
            'caries_risk_factors' => ['nullable', 'array', 'max:20'],
            'caries_risk_factors.*' => ['string', 'max:100'],
        ]);

        $odontogram->update($validated + ['caries_risk_assessed_at' => now()]);
        $this->auditLogger->logTenant('odontogram.caries_risk_updated', 'Odontogram', $odontogram->id, [
            'patient_id' => $patientId,
            'risk_level' => $validated['caries_risk_level'],
        ]);

        return back()->with('success', 'Riesgo de caries actualizado.');
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
