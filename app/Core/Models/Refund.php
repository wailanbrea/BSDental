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
 * @property string $patient_id
 * @property string|null $cash_session_id
 * @property float $amount
 * @property string $reason
 * @property Carbon $refunded_at
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Payment $payment
 * @property Patient $patient
 * @property CashSession|null $cashSession
 * @property User|null $createdBy
 */
class Refund extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'refunds';

    protected $fillable = [
        'id',
        'payment_id',
        'patient_id',
        'cash_session_id',
        'amount',
        'reason',
        'refunded_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'refunded_at' => 'datetime',
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
}
