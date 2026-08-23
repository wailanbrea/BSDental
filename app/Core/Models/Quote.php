<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string|null $professional_id
 * @property string $quote_number
 * @property int $version
 * @property string $alternative_name
 * @property string $status
 * @property float $subtotal
 * @property float $discount_total
 * @property float $tax_total
 * @property float $grand_total
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property Carbon|null $approved_at
 * @property string|null $approved_by_name
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property Professional|null $professional
 * @property Collection<int, QuoteItem> $items
 */
class Quote extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'quotes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'professional_id',
        'quote_number',
        'version',
        'alternative_name',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'notes',
        'expires_at',
        'approved_at',
        'approved_by_name',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'subtotal' => 'float',
            'discount_total' => 'float',
            'tax_total' => 'float',
            'grand_total' => 'float',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
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
     * Professional relation.
     *
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    /**
     * Items relation.
     *
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class, 'quote_id');
    }
}
