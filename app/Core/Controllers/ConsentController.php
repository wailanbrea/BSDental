<?php

namespace App\Core\Controllers;

use App\Core\Models\ConsentTemplate;
use App\Core\Models\Patient;
use App\Core\Models\PatientConsent;
use App\Core\Services\ConsentSigningService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class ConsentController extends Controller
{
    public function __construct(
        protected ConsentSigningService $consentService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display patient's consents and available templates.
     */
    public function index(string $patientId): Response
    {
        $patient = Patient::with('medicalHistory')->findOrFail($patientId);
        $templates = ConsentTemplate::where('is_active', true)->orderBy('title')->get();
        $consents = PatientConsent::where('patient_id', $patientId)->orderBy('signed_at', 'desc')->get();

        return Inertia::render('Clinic/Consents/Index', [
            'patient' => $patient,
            'templates' => $templates->map(fn (ConsentTemplate $template): array => [
                'id' => $template->id,
                'title' => $template->title,
                'slug' => $template->slug,
                'version' => $template->version,
                'content' => $template->content,
                'required_witness' => $template->required_witness,
            ]),
            'consents' => $consents->map(function (PatientConsent $consent): array {
                $integrity = $this->consentService->verify($consent);

                return [
                    'id' => $consent->id,
                    'title' => $consent->title,
                    'template_version' => $consent->template_version,
                    'signed_by_name' => $consent->signed_by_name,
                    'signed_by_identification' => $consent->signed_by_identification,
                    'relationship' => $consent->relationship,
                    'signature_type' => $consent->signature_type,
                    'signed_at' => $consent->signed_at->toIso8601String(),
                    'integrity_hash' => $consent->integrity_hash,
                    'rendered_content' => $consent->rendered_content,
                    'integrity' => $integrity,
                ];
            }),
        ]);
    }

    /**
     * Sign and store an informed consent.
     */
    public function store(Request $request, string $patientId): RedirectResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate([
            'consent_template_id' => ['required', 'uuid', 'exists:tenant.consent_templates,id'],
            'signed_by_name' => ['required', 'string', 'max:255'],
            'signed_by_identification' => ['required', 'string', 'max:50'],
            'relationship' => ['required', 'string', 'in:patient,parent,guardian,legal_representative'],
            'signature_type' => ['required', 'string', 'in:drawn,digital,biometric'],
            'signature_data' => ['required', 'string', 'max:1500000', 'regex:/\Adata:image\/(?:png|svg\+xml);base64,/'],
            'accepted_terms' => ['accepted'],
        ]);

        $template = ConsentTemplate::findOrFail($validated['consent_template_id']);

        try {
            $consent = $this->consentService->sign(
                $patient,
                $template,
                $validated['signed_by_name'],
                $validated['signed_by_identification'] ?? null,
                $validated['relationship'],
                $validated['signature_type'],
                $validated['signature_data'],
                $request->ip()
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        $this->auditLogger->logTenant('consent.signed', 'PatientConsent', $consent->id, [
            'patient_id' => $patient->id,
            'template_title' => $consent->title,
            'integrity_hash' => $consent->integrity_hash,
        ]);

        return redirect()->back()->with('success', 'Consentimiento informado firmado y sellado inmutablemente.');
    }
}
