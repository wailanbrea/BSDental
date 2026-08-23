<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $record_number
 * @property string $first_name
 * @property string $last_name
 * @property string|null $identification_type
 * @property string|null $identification_number
 * @property Carbon|null $birth_date
 * @property string|null $gender
 * @property string|null $phone
 * @property string|null $secondary_phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $city
 * @property string|null $occupation
 * @property string|null $blood_type
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_contact_phone
 * @property string|null $emergency_contact_relationship
 * @property bool $is_minor
 * @property string|null $guardian_name
 * @property string|null $guardian_identification
 * @property string|null $guardian_phone
 * @property string|null $insurance_company
 * @property string|null $insurance_policy_number
 * @property string|null $source
 * @property string|null $notes
 * @property list<string>|null $tags
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $full_name
 * @property-read int|null $age
 * @property PatientMedicalHistory|null $medicalHistory
 * @property Collection<int, PatientFile> $files
 * @property Collection<int, Appointment> $appointments
 * @property Collection<int, ClinicalEncounter> $clinicalEncounters
 * @property Collection<int, Odontogram> $odontograms
 * @property Collection<int, TreatmentPlan> $treatmentPlans
 * @property Collection<int, Quote> $quotes
 * @property Collection<int, PatientCharge> $charges
 * @property Collection<int, Payment> $payments
 * @property Collection<int, PatientConsent> $consents
 */
class Patient extends Model
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
    protected $table = 'patients';

    /**
     * Accessors included in Inertia payloads.
     *
     * @var list<string>
     */
    protected $appends = [
        'full_name',
        'age',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'record_number',
        'first_name',
        'last_name',
        'identification_type',
        'identification_number',
        'birth_date',
        'gender',
        'phone',
        'secondary_phone',
        'email',
        'address',
        'city',
        'occupation',
        'blood_type',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'is_minor',
        'guardian_name',
        'guardian_identification',
        'guardian_phone',
        'insurance_company',
        'insurance_policy_number',
        'source',
        'notes',
        'tags',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_minor' => 'boolean',
            'tags' => 'array',
        ];
    }

    /**
     * Full name accessor.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Age accessor.
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    /**
     * Medical history / Anamnesis.
     *
     * @return HasOne<PatientMedicalHistory, $this>
     */
    public function medicalHistory(): HasOne
    {
        return $this->hasOne(PatientMedicalHistory::class, 'patient_id');
    }

    /**
     * Associated patient files and scans.
     *
     * @return HasMany<PatientFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(PatientFile::class, 'patient_id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    /** @return HasMany<ClinicalEncounter, $this> */
    public function clinicalEncounters(): HasMany
    {
        return $this->hasMany(ClinicalEncounter::class, 'patient_id');
    }

    /** @return HasMany<Odontogram, $this> */
    public function odontograms(): HasMany
    {
        return $this->hasMany(Odontogram::class, 'patient_id');
    }

    /** @return HasMany<TreatmentPlan, $this> */
    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(TreatmentPlan::class, 'patient_id');
    }

    /** @return HasMany<Quote, $this> */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'patient_id');
    }

    /** @return HasMany<PatientCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(PatientCharge::class, 'patient_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'patient_id');
    }

    /** @return HasMany<PatientConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(PatientConsent::class, 'patient_id');
    }
}
