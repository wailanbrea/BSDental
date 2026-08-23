<?php

namespace App\Core\Services;

use App\Core\Models\FollowUpTask;
use App\Core\Models\Patient;
use Carbon\Carbon;
use InvalidArgumentException;

class FollowUpTaskService
{
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
        ?string $notes = null
    ): FollowUpTask {
        return FollowUpTask::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointmentId,
            'type' => $type,
            'title' => $title,
            'due_date' => $dueDate,
            'priority' => $priority,
            'status' => 'pending',
            'notes' => $notes,
            'assigned_to_user_id' => $assignedUserId,
        ]);
    }

    /**
     * Mark task as completed.
     */
    public function completeTask(FollowUpTask $task): FollowUpTask
    {
        if ($task->status === 'completed') {
            throw new InvalidArgumentException('Esta tarea ya fue completada.');
        }

        $task->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $task;
    }
}
