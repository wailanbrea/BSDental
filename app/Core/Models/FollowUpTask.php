<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string|null $appointment_id
 * @property string $type
 * @property string $title
 * @property Carbon $due_date
 * @property string $priority
 * @property string $status
 * @property string|null $notes
 * @property string|null $assigned_to_user_id
 * @property Carbon|null $completed_at
 * @property string|null $completion_channel
 * @property string|null $completion_result
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property Appointment|null $appointment
 * @property User|null $assignedTo
 */
class FollowUpTask extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'follow_up_tasks';

    protected $fillable = [
        'id',
        'patient_id',
        'appointment_id',
        'treatment_execution_id',
        'type',
        'title',
        'due_date',
        'priority',
        'status',
        'notes',
        'assigned_to_user_id',
        'completed_at',
        'completion_channel',
        'completion_result',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Patient relation.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Appointment relation.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Assigned to user relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}
