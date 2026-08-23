<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $encounter_id
 * @property string|null $subjective
 * @property string|null $objective
 * @property string|null $assessment
 * @property string|null $plan
 * @property string|null $treatment_performed
 * @property string|null $recommendations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ClinicalEncounter $encounter
 */
class ClinicalEvolution extends Model
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
    protected $table = 'clinical_evolutions';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'encounter_id',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'treatment_performed',
        'recommendations',
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
