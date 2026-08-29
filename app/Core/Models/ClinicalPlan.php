<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalPlan extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'clinical_plans';

    protected $fillable = [
        'patient_id',
        'clinical_encounter_id',
        'clinical_diagnosis_id',
        'title',
        'status',
        'notes',
        'created_by_user_id',
    ];

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** @return BelongsTo<ClinicalEncounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id');
    }

    /** @return BelongsTo<ClinicalDiagnosis, $this> */
    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(ClinicalDiagnosis::class, 'clinical_diagnosis_id');
    }

    /** @return HasMany<ClinicalPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ClinicalPlanItem::class)->orderBy('created_at');
    }
}
