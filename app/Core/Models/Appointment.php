<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $professional_id
 * @property string $branch_id
 * @property string|null $room_id
 * @property string|null $appointment_type_id
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property int $duration_minutes
 * @property string $status
 * @property string|null $reason
 * @property string|null $notes
 * @property string|null $cancellation_reason
 * @property string|null $rescheduled_from_id
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $in_progress_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property Professional $professional
 * @property Branch $branch
 * @property Room|null $room
 * @property AppointmentType|null $appointmentType
 */
class Appointment extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'appointments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'professional_id',
        'branch_id',
        'room_id',
        'appointment_type_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
        'reason',
        'notes',
        'cancellation_reason',
        'rescheduled_from_id',
        'checked_in_at',
        'in_progress_at',
        'completed_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'duration_minutes' => 'integer',
            'checked_in_at' => 'datetime',
            'in_progress_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * Professional relation.
     *
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    /**
     * Branch relation.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Room / Dental chair relation.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /**
     * Appointment Type relation.
     *
     * @return BelongsTo<AppointmentType, $this>
     */
    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class, 'appointment_type_id');
    }
}
