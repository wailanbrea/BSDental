<?php

namespace App\Core\Controllers;

use App\Core\Models\CrmStage;
use App\Core\Models\FollowUpTask;
use App\Core\Models\NotificationLog;
use App\Core\Models\Patient;
use App\Core\Services\FollowUpTaskService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
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
}
