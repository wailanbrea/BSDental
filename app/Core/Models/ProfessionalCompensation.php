<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $professional_id
 * @property string|null $patient_charge_id
 * @property string $rule_type
 * @property float $rate
 * @property float $base_amount
 * @property float $commission_amount
 * @property string $status
 * @property Carbon $accrued_at
 * @property Carbon|null $settled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Professional $professional
 * @property PatientCharge|null $charge
 */
class ProfessionalCompensation extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'professional_compensations';

    protected $fillable = [
        'id',
        'professional_id',
        'patient_charge_id',
        'rule_type',
        'rate',
        'base_amount',
        'commission_amount',
        'status',
        'accrued_at',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'base_amount' => 'float',
            'commission_amount' => 'float',
            'accrued_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
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
     * Charge relation.
     *
     * @return BelongsTo<PatientCharge, $this>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(PatientCharge::class, 'patient_charge_id');
    }
}
