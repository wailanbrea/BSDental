<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $cash_session_id
 * @property string $type
 * @property float $amount
 * @property string $payment_method
 * @property string $concept
 * @property string|null $created_by_user_id
 * @property Carbon $created_at
 * @property CashSession $cashSession
 * @property User|null $createdBy
 */
class CashMovement extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'cash_movements';

    protected $fillable = [
        'id',
        'cash_session_id',
        'type',
        'amount',
        'payment_method',
        'concept',
        'idempotency_key',
        'created_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'created_at' => 'datetime',
        ];
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
