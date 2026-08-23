<?php

namespace App\Core\Controllers;

use App\Core\Models\Branch;
use App\Core\Models\Patient;
use App\Core\Models\PatientFile;
use App\Core\Models\PatientMedicalHistory;
use App\Core\Services\PatientDuplicateDetector;
use App\Core\Services\PatientRecordNumberGenerator;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use App\Platform\Security\Uploads\SecureUploadService;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    public function __construct(
        protected PatientRecordNumberGenerator $recordNumberGenerator,
        protected PatientDuplicateDetector $duplicateDetector,
        protected SecureUploadService $uploadService,
        protected AuditLogger $auditLogger,
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Display a listing of patients with server-side search.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $branchId = $request->input('branch_id');
        $tag = $request->input('tag');
        $lastVisit = $request->input('last_visit');

        $query = Patient::with(['medicalHistory'])
            ->withMax([
                'appointments as last_visit_at' => fn ($q) => $q->where('status', 'completed'),
            ], 'start_time')
            ->withMin([
                'appointments as next_appointment_at' => fn ($q) => $q
                    ->whereIn('status', ['scheduled', 'confirmed', 'checked_in', 'waiting'])
                    ->where('start_time', '>=', now()),
            ], 'start_time')
            ->withSum('charges as balance_due', 'balance_due')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if (! empty($search)) {
            $term = trim((string) $search);
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('record_number', 'like', "%{$term}%")
                    ->orWhere('identification_number', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if (! empty($branchId)) {
            $query->whereHas('appointments', fn ($q) => $q->where('branch_id', $branchId));
        }

        if (! empty($tag)) {
            $query->whereJsonContains('tags', $tag);
        }

        if (in_array($lastVisit, ['30', '90', '365'], true)) {
            $query->whereHas('appointments', fn ($q) => $q
                ->where('status', 'completed')
                ->where('start_time', '>=', now()->subDays((int) $lastVisit)));
        }

        $patients = $query->paginate(20)->withQueryString();

        return Inertia::render('Clinic/Patients/Index', [
            'patients' => $patients,
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
                'tag' => $tag,
                'last_visit' => $lastVisit,
            ],
        ]);
    }

    /**
     * Show the form for creating a new patient.
     */
    public function create(): Response
    {
        return Inertia::render('Clinic/Patients/Create', [
            'suggestedRecordNumber' => $this->recordNumberGenerator->generate(),
        ]);
    }

    /**
     * Search for duplicate candidates.
     */
    public function checkDuplicates(Request $request): JsonResponse
    {
        $candidates = $this->duplicateDetector->findCandidates(
            $request->input('identification_number'),
            $request->input('phone'),
            $request->input('first_name'),
            $request->input('last_name'),
            $request->input('ignore_id')
        );

        return response()->json([
            'candidates' => $candidates->map(fn (Patient $p) => [
                'id' => $p->id,
                'record_number' => $p->record_number,
                'full_name' => $p->full_name,
                'identification_number' => $p->identification_number,
                'phone' => $p->phone,
            ]),
        ]);
    }

    /**
     * Store a newly created patient.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'identification_type' => ['nullable', 'string', 'max:50'],
            'identification_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'is_minor' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if:is_minor,true', 'string', 'max:255'],
            'guardian_identification' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'required_if:is_minor,true', 'string', 'max:50'],
            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_policy_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            // Medical history
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:100'],
            'systemic_conditions' => ['nullable', 'array'],
            'systemic_conditions.*' => ['string', 'max:100'],
            'current_medications' => ['nullable', 'array'],
            'current_medications.*' => ['string', 'max:150'],
            'is_pregnant' => ['boolean'],
            'pregnancy_weeks' => ['nullable', 'required_if:is_pregnant,true', 'integer', 'min:1', 'max:42'],
            'bleeding_disorders' => ['boolean'],
            'has_pacemaker' => ['boolean'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        /** @var Patient $patient */
        $patient = DB::connection('tenant')->transaction(function () use ($validated) {
            $recordNumber = $this->recordNumberGenerator->generate();

            $patient = Patient::create([
                'record_number' => $recordNumber,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'identification_type' => $validated['identification_type'] ?? null,
                'identification_number' => $validated['identification_number'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'secondary_phone' => $validated['secondary_phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'is_minor' => $validated['is_minor'] ?? false,
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_identification' => $validated['guardian_identification'] ?? null,
                'guardian_phone' => $validated['guardian_phone'] ?? null,
                'insurance_company' => $validated['insurance_company'] ?? null,
                'insurance_policy_number' => $validated['insurance_policy_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
                'status' => 'active',
            ]);

            PatientMedicalHistory::create([
                'patient_id' => $patient->id,
                'allergies' => $validated['allergies'] ?? [],
                'systemic_conditions' => $validated['systemic_conditions'] ?? [],
                'current_medications' => $validated['current_medications'] ?? [],
                'is_pregnant' => $validated['is_pregnant'] ?? false,
                'pregnancy_weeks' => $validated['pregnancy_weeks'] ?? null,
                'bleeding_disorders' => $validated['bleeding_disorders'] ?? false,
                'has_pacemaker' => $validated['has_pacemaker'] ?? false,
                'medical_notes' => $validated['medical_notes'] ?? null,
            ]);

            return $patient;
        });

        $this->auditLogger->logTenant('patient.created', 'Patient', $patient->id, [
            'record_number' => $patient->record_number,
            'name' => $patient->full_name,
        ]);

        return redirect()->route('clinic.patients.show', $patient->id)
            ->with('success', 'Paciente registrado exitosamente.');
    }

    /**
     * Display the 360 patient profile.
     */
    public function show(string $id): Response
    {
        $patient = Patient::with([
            'medicalHistory',
            'files.uploader',
            'appointments' => fn ($q) => $q->with(['professional', 'appointmentType', 'room'])->latest('start_time'),
            'clinicalEncounters' => fn ($q) => $q
                ->with(['professional', 'evolution', 'diagnoses', 'amendments'])
                ->latest('encounter_date'),
            'odontograms' => fn ($q) => $q->with('entries')->latest(),
            'treatmentPlans' => fn ($q) => $q->with(['items.procedure', 'quote'])->latest(),
            'quotes' => fn ($q) => $q->with(['items.procedure', 'professional'])->latest(),
            'charges' => fn ($q) => $q->latest(),
            'payments' => fn ($q) => $q->with('splits')->latest('paid_at'),
            'consents' => fn ($q) => $q->with('template')->latest('signed_at'),
        ])->findOrFail($id);

        return Inertia::render('Clinic/Patients/Show', [
            'patient' => $patient,
            'summary' => [
                'balance_due' => round((float) $patient->charges->sum('balance_due'), 2),
                'next_appointment' => $patient->appointments
                    ->first(fn ($appointment) => $appointment->start_time->isFuture()
                        && in_array($appointment->status, ['scheduled', 'confirmed', 'checked_in', 'waiting'], true)),
                'active_treatment_plan' => $patient->treatmentPlans
                    ->first(fn ($plan) => in_array($plan->status, ['active', 'in_progress'], true)),
                'latest_encounter' => $patient->clinicalEncounters->first(),
            ],
        ]);
    }

    /**
     * Show the form for editing a patient and their medical history.
     */
    public function edit(string $id): Response
    {
        $patient = Patient::with('medicalHistory')->findOrFail($id);

        return Inertia::render('Clinic/Patients/Create', [
            'suggestedRecordNumber' => $patient->record_number,
            'patient' => $patient,
        ]);
    }

    /**
     * Update the patient data.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'identification_type' => ['nullable', 'string', 'max:50'],
            'identification_number' => ['nullable', 'string', 'max:50'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', 'string', 'max:10'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'is_minor' => ['boolean'],
            'guardian_name' => ['nullable', 'required_if:is_minor,true', 'string', 'max:255'],
            'guardian_identification' => ['nullable', 'string', 'max:50'],
            'guardian_phone' => ['nullable', 'required_if:is_minor,true', 'string', 'max:50'],
            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_policy_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            // Medical history
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:100'],
            'systemic_conditions' => ['nullable', 'array'],
            'systemic_conditions.*' => ['string', 'max:100'],
            'current_medications' => ['nullable', 'array'],
            'current_medications.*' => ['string', 'max:150'],
            'is_pregnant' => ['boolean'],
            'pregnancy_weeks' => ['nullable', 'required_if:is_pregnant,true', 'integer', 'min:1', 'max:42'],
            'bleeding_disorders' => ['boolean'],
            'has_pacemaker' => ['boolean'],
            'medical_notes' => ['nullable', 'string'],
        ]);

        DB::connection('tenant')->transaction(function () use ($patient, $validated) {
            $patient->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'identification_type' => $validated['identification_type'] ?? null,
                'identification_number' => $validated['identification_number'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'secondary_phone' => $validated['secondary_phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'blood_type' => $validated['blood_type'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
                'is_minor' => $validated['is_minor'] ?? false,
                'guardian_name' => $validated['guardian_name'] ?? null,
                'guardian_identification' => $validated['guardian_identification'] ?? null,
                'guardian_phone' => $validated['guardian_phone'] ?? null,
                'insurance_company' => $validated['insurance_company'] ?? null,
                'insurance_policy_number' => $validated['insurance_policy_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'tags' => $validated['tags'] ?? [],
            ]);

            $patient->medicalHistory()->updateOrCreate(
                ['patient_id' => $patient->id],
                [
                    'allergies' => $validated['allergies'] ?? [],
                    'systemic_conditions' => $validated['systemic_conditions'] ?? [],
                    'current_medications' => $validated['current_medications'] ?? [],
                    'is_pregnant' => $validated['is_pregnant'] ?? false,
                    'pregnancy_weeks' => $validated['pregnancy_weeks'] ?? null,
                    'bleeding_disorders' => $validated['bleeding_disorders'] ?? false,
                    'has_pacemaker' => $validated['has_pacemaker'] ?? false,
                    'medical_notes' => $validated['medical_notes'] ?? null,
                ]
            );
        });

        $this->auditLogger->logTenant('patient.updated', 'Patient', $patient->id, [
            'name' => $patient->full_name,
        ]);

        return redirect()->route('clinic.patients.show', $patient->id)
            ->with('success', 'Ficha clínica del paciente actualizada.');
    }

    /**
     * Upload and attach a clinical file to patient.
     */
    public function uploadFile(Request $request, string $id): RedirectResponse
    {
        $patient = Patient::findOrFail($id);

        $request->validate([
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'category' => ['required', 'string', 'in:radiography,lab_result,document,consent,photo'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('file');
        if (! $file) {
            return redirect()->back()->withErrors(['file' => 'Archivo no proporcionado']);
        }

        $stored = $this->uploadService->store($file, $request->input('category', 'documents'));

        $patient->files()->create([
            'category' => $request->input('category'),
            'filename' => $stored['filename'],
            'original_name' => $stored['original_name'],
            'mime_type' => $stored['mime_type'],
            'size_bytes' => $stored['size_bytes'],
            'stored_path' => $stored['stored_path'],
            'notes' => $request->input('notes'),
            'uploaded_by_user_id' => Auth::guard('web')->id(),
        ]);

        $this->auditLogger->logTenant('patient.file_uploaded', 'PatientFile', $patient->id, [
            'original_name' => $stored['original_name'],
            'category' => $request->input('category'),
        ]);

        return redirect()->back()->with('success', 'Archivo clínico subido y adjuntado con éxito.');
    }

    /** Display a private clinical file inline when the browser supports its MIME type. */
    public function viewFile(string $id): StreamedResponse
    {
        return $this->serveFile($id, 'inline');
    }

    /** Download a private clinical file through the authenticated tenant context. */
    public function downloadFile(string $id): StreamedResponse
    {
        return $this->serveFile($id, 'attachment');
    }

    private function serveFile(string $id, string $disposition): StreamedResponse
    {
        $file = PatientFile::findOrFail($id);
        $tenant = $this->tenantContext->requireCurrent();
        $expectedPrefix = "tenants/{$tenant->id}/uploads/";

        if (! str_starts_with($file->stored_path, $expectedPrefix)) {
            abort(404);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($file->stored_path)) {
            abort(404);
        }

        $this->auditLogger->logTenant(
            $disposition === 'attachment' ? 'patient.file_downloaded' : 'patient.file_viewed',
            'PatientFile',
            $file->id,
            ['patient_id' => $file->patient_id, 'mime_type' => $file->mime_type]
        );

        return $disk->response(
            $file->stored_path,
            $file->original_name,
            ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff'],
            $disposition
        );
    }

    /**
     * Remove the specified patient (soft delete).
     */
    public function destroy(string $id): RedirectResponse
    {
        $patient = Patient::findOrFail($id);
        $patient->delete();

        $this->auditLogger->logTenant('patient.deleted', 'Patient', $patient->id, [
            'record_number' => $patient->record_number,
            'name' => $patient->full_name,
        ]);

        return redirect()->route('clinic.patients.index')
            ->with('success', 'Paciente archivado exitosamente.');
    }
}
