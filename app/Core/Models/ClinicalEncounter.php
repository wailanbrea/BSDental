<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $professional_id
 * @property string|null $appointment_id
 * @property Carbon $encounter_date
 * @property string|null $chief_complaint
 * @property string|null $physical_examination
 * @property array<string, mixed>|null $vital_signs
 * @property string $status
 * @property Carbon|null $finalized_at
 * @property string|null $finalized_by_user_id
 * @property string|null $integrity_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property Professional $professional
 * @property Appointment|null $appointment
 * @property User|null $finalizedBy
 * @property ClinicalEvolution|null $evolution
 * @property Collection<int, ClinicalDiagnosis> $diagnoses
 * @property Collection<int, ClinicalPrescription> $prescriptions
 * @property Collection<int, ClinicalAmendment> $amendments
 */
class ClinicalEncounter extends Model
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
    protected $table = 'clinical_encounters';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'professional_id',
        'appointment_id',
        'encounter_date',
        'chief_complaint',
        'physical_examination',
        'vital_signs',
        'status',
        'finalized_at',
        'finalized_by_user_id',
        'integrity_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encounter_date' => 'datetime',
            'vital_signs' => 'array',
            'finalized_at' => 'datetime',
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
     * Appointment relation.
     *
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Finalizer user.
     *
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * SOAP evolution.
     *
     * @return HasOne<ClinicalEvolution, $this>
     */
    public function evolution(): HasOne
    {
        return $this->hasOne(ClinicalEvolution::class, 'encounter_id');
    }

    /**
     * Diagnoses list.
     *
     * @return HasMany<ClinicalDiagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(ClinicalDiagnosis::class, 'encounter_id');
    }

    /**
     * Prescriptions list.
     *
     * @return HasMany<ClinicalPrescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(ClinicalPrescription::class, 'encounter_id');
    }

    /**
     * Amendments list.
     *
     * @return HasMany<ClinicalAmendment, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(ClinicalAmendment::class, 'encounter_id')->orderBy('amended_at', 'desc');
    }
}
