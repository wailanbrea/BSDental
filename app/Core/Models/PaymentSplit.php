<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $payment_id
 * @property string $method
 * @property float $amount
 * @property string|null $reference_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Payment $payment
 */
class PaymentSplit extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'payment_splits';

    protected $fillable = [
        'id',
        'payment_id',
        'method',
        'amount',
        'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
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
}
