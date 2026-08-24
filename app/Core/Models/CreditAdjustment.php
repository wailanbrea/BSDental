<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $patient_charge_id
 * @property string $patient_id
 * @property string $credit_note_number
 * @property string $type
 * @property float $amount
 * @property string $reason
 * @property Carbon $adjusted_at
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PatientCharge $charge
 * @property Patient $patient
 * @property User|null $createdBy
 */
class CreditAdjustment extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'credit_adjustments';

    protected $fillable = [
        'id',
        'patient_charge_id',
        'patient_id',
        'credit_note_number',
        'type',
        'amount',
        'reason',
        'adjusted_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'adjusted_at' => 'datetime',
        ];
    }

    /**
     * Charge relation.
     *
     * @return BelongsTo<PatientCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PatientCharge::class, 'patient_charge_id');
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
     * Created by relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
