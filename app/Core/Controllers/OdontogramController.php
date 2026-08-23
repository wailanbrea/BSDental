<?php

namespace App\Core\Controllers;

use App\Core\Models\Odontogram;
use App\Core\Models\OdontogramEntry;
use App\Core\Models\Patient;
use App\Core\Services\OdontogramService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $patient = Patient::findOrFail($patientId);
        $odontogram = $this->odontogramService->getOrCreateForPatient($patientId);
        $matrix = $this->odontogramService->getToothMatrix($odontogram);

        $entries = OdontogramEntry::with(['recordedBy', 'encounter'])
            ->where('odontogram_id', $odontogram->id)
            ->orderBy('recorded_at', 'desc')
            ->get();

        return Inertia::render('Clinic/Odontogram/Show', [
            'patient' => $patient,
            'odontogram' => $odontogram,
            'matrix' => $matrix,
            'entries' => $entries,
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
            'tooth_number' => ['required', 'integer', 'min:11', 'max:85'],
            'surface' => ['required', 'string', 'in:all,vestibular,lingual_palatal,mesial,distal,occlusal_incisal'],
            'condition' => ['required', 'string', 'in:caries,restored_composite,restored_amalgam,crown,endodontic,missing,implant,prosthesis,sealant,fracture,healthy'],
            'lifecycle_state' => ['required', 'string', 'in:initial_diagnosis,planned,approved,completed'],
            'notes' => ['nullable', 'string', 'max:500'],
            'encounter_id' => ['nullable', 'uuid', 'exists:tenant.clinical_encounters,id'],
        ]);

        $userId = Auth::guard('web')->id();

        $entry = $this->odontogramService->recordCondition(
            $odontogram,
            $validated['tooth_number'],
            $validated['condition'],
            $validated['surface'],
            $validated['lifecycle_state'],
            $validated['notes'] ?? null,
            $validated['encounter_id'] ?? null,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('odontogram.entry_created', 'OdontogramEntry', $entry->id, [
            'patient_id' => $patient->id,
            'tooth_number' => $entry->tooth_number,
            'condition' => $entry->condition,
            'surface' => $entry->surface,
            'lifecycle_state' => $entry->lifecycle_state,
        ]);

        return redirect()->back()->with('success', "Pieza {$entry->tooth_number} actualizada ({$entry->condition}).");
    }
}
