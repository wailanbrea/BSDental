<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $patient_id
 * @property string|null $treatment_plan_item_id
 * @property string|null $professional_id
 * @property string $charge_number
 * @property string $concept
 * @property float $amount
 * @property float $tax_amount
 * @property float $total_amount
 * @property float $paid_amount
 * @property float $balance_due
 * @property string $status
 * @property Carbon|null $due_date
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property TreatmentPlanItem|null $treatmentPlanItem
 * @property Professional|null $professional
 * @property User|null $createdBy
 * @property Collection<int, PaymentAllocation> $allocations
 */
class PatientCharge extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'patient_charges';

    protected $fillable = [
        'id',
        'patient_id',
        'treatment_plan_item_id',
        'professional_id',
        'charge_number',
        'concept',
        'amount',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'balance_due',
        'status',
        'due_date',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'tax_amount' => 'float',
            'total_amount' => 'float',
            'paid_amount' => 'float',
            'balance_due' => 'float',
            'due_date' => 'date',
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
     * Treatment plan item relation.
     *
     * @return BelongsTo<TreatmentPlanItem, $this>
     */
    public function treatmentPlanItem(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlanItem::class, 'treatment_plan_item_id');
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
     * Created by relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Allocations relation.
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'patient_charge_id');
    }
}
