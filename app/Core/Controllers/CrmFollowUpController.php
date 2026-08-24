<?php

namespace App\Core\Controllers;

use App\Core\Models\CrmStage;
use App\Core\Models\FollowUpTask;
use App\Core\Models\NotificationLog;
use App\Core\Models\Patient;
use App\Core\Models\PatientCrmProfile;
use App\Core\Services\FollowUpTaskService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CrmFollowUpController extends Controller
{
    public function __construct(
        protected FollowUpTaskService $followUpService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display CRM & Follow-up dashboard.
     */
    public function index(): Response
    {
        $tasks = FollowUpTask::with(['patient', 'appointment', 'assignedTo'])
            ->orderBy('due_date', 'asc')
            ->get();

        $stages = CrmStage::withCount('profiles')
            ->orderBy('order_index', 'asc')
            ->get();

        $notifications = NotificationLog::with(['patient', 'appointment'])
            ->orderBy('scheduled_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Clinic/Crm/Index', [
            'tasks' => $tasks,
            'stages' => $stages,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Store a new follow-up task.
     */
    public function storeTask(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid', 'exists:tenant.patients,id'],
            'type' => ['required', 'string', 'in:post_op,no_show,quote_pending,treatment_incomplete,periodic_recall'],
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'priority' => ['required', 'string', 'in:low,medium,high'],
            'appointment_id' => ['nullable', 'uuid', 'exists:tenant.appointments,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $userId = Auth::guard('web')->id();

        $task = $this->followUpService->createTask(
            $patient,
            $validated['type'],
            $validated['title'],
            Carbon::parse($validated['due_date']),
            $validated['priority'],
            $validated['appointment_id'] ?? null,
            $userId ? (string) $userId : null,
            $validated['notes'] ?? null
        );

        $this->auditLogger->logTenant('crm.task_created', 'FollowUpTask', $task->id, [
            'patient_id' => $patient->id,
            'type' => $task->type,
            'title' => $task->title,
        ]);

        return redirect()->back()->with('success', "Tarea de seguimiento '{$task->title}' programada.");
    }

    /**
     * Complete a follow-up task.
     */
    public function completeTask(string $id): RedirectResponse
    {
        $task = FollowUpTask::findOrFail($id);

        $this->followUpService->completeTask($task);

        $this->auditLogger->logTenant('crm.task_completed', 'FollowUpTask', $task->id, [
            'patient_id' => $task->patient_id,
            'title' => $task->title,
        ]);

        return redirect()->back()->with('success', "Tarea '{$task->title}' marcada como completada.");
    }

    /**
     * Update CRM pipeline profile stage & loss reason (CRM-01).
     */
    public function updateStage(Request $request, string $profileId): RedirectResponse
    {
        $profile = PatientCrmProfile::with(['patient', 'stage'])->findOrFail($profileId);

        $validated = $request->validate([
            'stage_id' => ['required', 'uuid', 'exists:tenant.crm_stages,id'],
            'loss_reason' => ['nullable', 'string', 'in:price,distance,treatment_postponed,competitor,unreachable,other'],
            'estimated_lifetime_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $newStage = CrmStage::findOrFail($validated['stage_id']);

        $profile->update([
            'stage_id' => $validated['stage_id'],
            'loss_reason' => $validated['loss_reason'] ?? null,
            'estimated_lifetime_value' => $validated['estimated_lifetime_value'] ?? $profile->estimated_lifetime_value,
            'notes' => $validated['notes'] ?? $profile->notes,
        ]);

        $this->auditLogger->logTenant('crm.stage_updated', 'PatientCrmProfile', $profile->id, [
            'patient_id' => $profile->patient_id,
            'stage' => $newStage->name,
            'loss_reason' => $validated['loss_reason'] ?? null,
        ]);

        return redirect()->back()->with('success', "Etapa de {$profile->patient->full_name} actualizada a '{$newStage->name}'.");
    }

    /**
     * Generate secure wa.me link for patient communication (CRM-02).
     */
    public function whatsappLink(Request $request, string $patientId): JsonResponse
    {
        $patient = Patient::findOrFail($patientId);

        $validated = $request->validate([
            'template' => ['nullable', 'string', 'in:reminder,post_op,quote_followup,custom'],
            'custom_message' => ['nullable', 'string', 'max:500'],
        ]);

        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $patient->phone);
        if (empty($cleanPhone)) {
            return response()->json(['error' => 'El paciente no posee un número telefónico válido.'], 422);
        }

        $template = $validated['template'] ?? 'reminder';
        $clinicName = 'BSDental';

        $text = match ($template) {
            'post_op' => "Hola {$patient->first_name}, le saludamos desde {$clinicName}. ¿Cómo se siente tras su procedimiento de hoy? Estamos atentos a su evolución.",
            'quote_followup' => "Hola {$patient->first_name}, le contactamos de {$clinicName} para saber si tiene alguna consulta sobre su plan de tratamiento y presupuesto.",
            'custom' => $validated['custom_message'] ?? "Hola {$patient->first_name}, le contactamos desde {$clinicName}.",
            default => "Hola {$patient->first_name}, le recordamos su cita en {$clinicName}. Por favor responda SI para confirmar o NO para reagendar.",
        };

        $encodedText = rawurlencode($text);
        $url = "https://wa.me/{$cleanPhone}?text={$encodedText}";

        return response()->json([
            'patient_name' => $patient->full_name,
            'phone' => $patient->phone,
            'whatsapp_opt_in' => $patient->whatsapp_opt_in,
            'whatsapp_url' => $url,
            'message_preview' => $text,
        ]);
    }
}
