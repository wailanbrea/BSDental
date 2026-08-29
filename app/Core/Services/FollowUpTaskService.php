<?php

namespace App\Core\Services;

use App\Core\Models\FollowUpTask;
use App\Core\Models\Patient;
use App\Core\Models\TreatmentExecution;
use Carbon\Carbon;
use InvalidArgumentException;

class FollowUpTaskService
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {}

    /**
     * Create a clinical recall or follow-up task.
     */
    public function createTask(
        Patient $patient,
        string $type,
        string $title,
        Carbon $dueDate,
        string $priority = 'medium',
        ?string $appointmentId = null,
        ?string $assignedUserId = null,
        ?string $notes = null,
        ?TreatmentExecution $execution = null,
    ): FollowUpTask {
        $task = FollowUpTask::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'treatment_execution_id' => $execution?->id,
            'type' => $type,
            'title' => $title,
            'due_date' => $dueDate,
            'priority' => $priority,
            'status' => 'pending',
            'notes' => $notes,
            'assigned_to_user_id' => $assignedUserId,
        ]);

        if ($assignedUserId !== null) {
            $this->notificationService->notifyUser(
                $assignedUserId,
                'follow_up',
                $priority === 'high' ? 'warning' : 'info',
                'Nueva tarea de seguimiento',
                "{$title} · {$patient->full_name}",
                '/crm',
                ['follow_up_task_id' => $task->id, 'patient_id' => $patient->id]
            );
        }

        return $task;
    }

    /**
     * Mark task as completed.
     */
    public function completeTask(FollowUpTask $task, ?string $completionChannel = null, ?string $completionResult = null): FollowUpTask
    {
        if ($task->status === 'completed') {
            throw new InvalidArgumentException('Esta tarea ya fue completada.');
        }

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completion_channel' => $completionChannel,
            'completion_result' => $completionResult,
        ]);

        return $task;
    }
}
