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
 * @property string $cash_register_id
 * @property string $opened_by_user_id
 * @property string|null $closed_by_user_id
 * @property string $status
 * @property float $opening_balance
 * @property float $expected_cash
 * @property float|null $counted_cash
 * @property float $difference
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $closing_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property CashRegister $cashRegister
 * @property User $openedBy
 * @property User|null $closedBy
 * @property Collection<int, CashMovement> $movements
 */
class CashSession extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'cash_sessions';

    protected $fillable = [
        'id',
        'cash_register_id',
        'opened_by_user_id',
        'closed_by_user_id',
        'status',
        'opening_balance',
        'expected_cash',
        'counted_cash',
        'difference',
        'opened_at',
        'closed_at',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'float',
            'expected_cash' => 'float',
            'counted_cash' => 'float',
            'difference' => 'float',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Cash register relation.
     *
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /**
     * Opened by relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * Closed by relation.
     *
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Movements relation.
     *
     * @return HasMany<CashMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'cash_session_id');
    }
}
