<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $laboratory_id
 * @property string|null $treatment_plan_item_id
 * @property string $order_number
 * @property int|null $tooth_number
 * @property string $work_description
 * @property string|null $shade_guide
 * @property string $status
 * @property Carbon|null $sent_date
 * @property Carbon|null $due_date
 * @property Carbon|null $received_date
 * @property float $estimated_cost
 * @property float $final_cost
 * @property string $payable_status
 * @property string|null $notes
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property DentalLaboratory $laboratory
 * @property TreatmentPlanItem|null $treatmentPlanItem
 * @property User|null $createdBy
 */
class LabOrder extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'lab_orders';

    protected $fillable = [
        'id',
        'patient_id',
        'laboratory_id',
        'treatment_plan_item_id',
        'parent_order_id',
        'order_number',
        'tooth_number',
        'work_description',
        'shade_guide',
        'status',
        'sent_date',
        'due_date',
        'received_date',
        'estimated_cost',
        'final_cost',
        'payable_status',
        'notes',
        'remake_reason',
        'quality_check_notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
            'sent_date' => 'date',
            'due_date' => 'date',
            'received_date' => 'date',
            'estimated_cost' => 'float',
            'final_cost' => 'float',
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
     * Laboratory relation.
     *
     * @return BelongsTo<DentalLaboratory, $this>
     */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(DentalLaboratory::class, 'laboratory_id');
    }

    /**
     * Treatment plan item relation.
     *
     * @return BelongsTo<TreatmentPlanItem, $this>
     */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
    }

    /**
     * Parent order relation for remakes.
     *
     * @return BelongsTo<LabOrder, $this>
     */
    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(LabOrder::class, 'parent_order_id');
    }

    /**
     * Remakes relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<LabOrder, $this>
     */
    public function remakes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LabOrder::class, 'parent_order_id');
    }

    /**
     * User relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
