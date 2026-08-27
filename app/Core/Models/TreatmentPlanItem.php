<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $treatment_plan_id
 * @property string $procedure_id
 * @property int|null $tooth_number
 * @property string $surface
 * @property int $phase
 * @property float $price
 * @property string $status
 * @property string|null $appointment_id
 * @property string|null $encounter_id
 * @property string|null $professional_id
 * @property Carbon|null $completed_at
 * @property string|null $completed_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property TreatmentPlan $treatmentPlan
 * @property Procedure $procedure
 * @property Appointment|null $appointment
 * @property ClinicalEncounter|null $encounter
 */
class TreatmentPlanItem extends Model
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
    protected $table = 'treatment_plan_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'treatment_plan_id',
        'procedure_id',
        'tooth_number',
        'surface',
        'phase',
        'price',
        'status',
        'appointment_id',
        'encounter_id',
        'professional_id',
        'completed_at',
        'completed_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
            'phase' => 'integer',
            'price' => 'float',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Treatment Plan relation.
     *
     * @return BelongsTo<TreatmentPlan, $this>
     */
    public function treatmentPlan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id');
    }

    /**
     * Procedure relation.
     *
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
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
     * Encounter relation.
     *
     * @return BelongsTo<ClinicalEncounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    public function compensation(): HasOne
    {
        return $this->hasOne(ProfessionalCompensation::class, 'treatment_plan_item_id');
    }
}
