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
 * @property string|null $cash_session_id
 * @property string $payment_number
 * @property float $total_amount
 * @property float $allocated_amount
 * @property float $unallocated_amount
 * @property float $refunded_amount
 * @property string $status
 * @property Carbon $paid_at
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property CashSession|null $cashSession
 * @property User|null $createdBy
 * @property Collection<int, PaymentSplit> $splits
 * @property Collection<int, PaymentAllocation> $allocations
 * @property Collection<int, Refund> $refunds
 */
class Payment extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'payments';

    protected $fillable = [
        'id',
        'patient_id',
        'cash_session_id',
        'payment_number',
        'total_amount',
        'allocated_amount',
        'unallocated_amount',
        'refunded_amount',
        'status',
        'idempotency_key',
        'paid_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'float',
            'allocated_amount' => 'float',
            'unallocated_amount' => 'float',
            'refunded_amount' => 'float',
            'paid_at' => 'datetime',
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
     * Cash session relation.
     *
     * @return BelongsTo<CashSession, $this>
     */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
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
     * Splits relation.
     *
     * @return HasMany<PaymentSplit, $this>
     */
    public function splits(): HasMany
    {
        return $this->hasMany(PaymentSplit::class, 'payment_id');
    }

    /**
     * Allocations relation.
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'payment_id');
    }

    /**
     * Refunds relation.
     *
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'payment_id');
    }

    /**
     * Get remaining refundable balance.
     */
    public function getRefundableBalance(): float
    {
        return max(0.0, $this->total_amount - $this->refunded_amount);
    }
}
