<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $patient_id
 * @property string|null $appointment_id
 * @property string $channel
 * @property string $recipient
 * @property string $status
 * @property string $content
 * @property string|null $provider_message_id
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property Appointment|null $appointment
 */
class NotificationLog extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'notification_logs';

    protected $fillable = [
        'id',
        'patient_id',
        'appointment_id',
        'channel',
        'recipient',
        'status',
        'content',
        'provider_message_id',
        'scheduled_at',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
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
}
