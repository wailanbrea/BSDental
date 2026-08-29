<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $payment_id
 * @property string $patient_charge_id
 * @property float $amount
 * @property float $reversed_amount
 * @property string|null $reason
 * @property string|null $idempotency_key
 * @property Carbon $allocated_at
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Payment $payment
 * @property PatientCharge $charge
 */
class PaymentAllocation extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'payment_allocations';

    protected $fillable = [
        'id',
        'payment_id',
        'patient_charge_id',
        'amount',
        'reversed_amount',
        'reason',
        'idempotency_key',
        'allocated_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'reversed_amount' => 'float',
            'allocated_at' => 'datetime',
        ];
    }

    /**
     * Amount of this allocation that remains applied to the charge.
     */
    public function getOpenAmount(): float
    {
        return max(0.0, round($this->amount - $this->reversed_amount, 2));
    }

    /**
     * Payment relation.
     *
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
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

    /**
     * User who authorized the manual application.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
