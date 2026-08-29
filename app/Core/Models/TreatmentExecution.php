<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreatmentExecution extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'id',
        'treatment_plan_item_id',
        'clinical_encounter_id',
        'professional_id',
        'executed_by_user_id',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TreatmentPlanItem, $this> */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class);
    }

    /** @return BelongsTo<ClinicalEncounter, $this> */
    public function clinicalEncounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<User, $this> */
    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }
}
