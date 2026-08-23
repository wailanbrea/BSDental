<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $encounter_id
 * @property string $medication_name
 * @property string $dosage
 * @property string $frequency
 * @property string $duration
 * @property string|null $instructions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ClinicalEncounter $encounter
 */
class ClinicalPrescription extends Model
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
    protected $table = 'clinical_prescriptions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'encounter_id',
        'medication_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
    ];

    /**
     * Encounter relation.
     *
     * @return BelongsTo<ClinicalEncounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }
}
