<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $payment_id
 * @property string $patient_charge_id
 * @property float $amount
 * @property Carbon $allocated_at
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
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'allocated_at' => 'datetime',
        ];
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
}
