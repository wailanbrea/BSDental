<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $patient_id
 * @property list<string>|null $allergies
 * @property list<string>|null $systemic_conditions
 * @property list<string>|null $current_medications
 * @property bool $is_pregnant
 * @property int|null $pregnancy_weeks
 * @property bool $bleeding_disorders
 * @property bool $has_pacemaker
 * @property string|null $medical_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 */
class PatientMedicalHistory extends Model
{
    use HasUuids;

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
    protected $table = 'patient_medical_histories';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'allergies',
        'systemic_conditions',
        'current_medications',
        'is_pregnant',
        'pregnancy_weeks',
        'bleeding_disorders',
        'has_pacemaker',
        'medical_notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allergies' => 'array',
            'systemic_conditions' => 'array',
            'current_medications' => 'array',
            'is_pregnant' => 'boolean',
            'pregnancy_weeks' => 'integer',
            'bleeding_disorders' => 'boolean',
            'has_pacemaker' => 'boolean',
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
}
