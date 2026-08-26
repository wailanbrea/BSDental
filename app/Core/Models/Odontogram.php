<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $type
 * @property string|null $notes
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property Collection<int, OdontogramEntry> $entries
 */
class Odontogram extends Model
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
    protected $table = 'odontograms';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'type',
        'notes',
        'caries_risk_level',
        'caries_risk_factors',
        'caries_risk_assessed_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'caries_risk_factors' => 'array',
            'caries_risk_assessed_at' => 'datetime',
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
     * Entries relation.
     *
     * @return HasMany<OdontogramEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(OdontogramEntry::class, 'odontogram_id')->orderBy('recorded_at', 'asc');
    }

    public function periodontalExams(): HasMany
    {
        return $this->hasMany(PeriodontalExam::class, 'odontogram_id');
    }
}
