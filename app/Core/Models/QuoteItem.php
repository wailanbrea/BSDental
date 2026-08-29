<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $quote_id
 * @property string $procedure_id
 * @property int|null $tooth_number
 * @property string $surface
 * @property float $unit_price
 * @property int $quantity
 * @property float $discount_percentage
 * @property float $subtotal
 * @property float $tax
 * @property float $total
 * @property bool $is_approved
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Quote $quote
 * @property Procedure $procedure
 */
class QuoteItem extends Model
{
    use HasUuids;

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
    protected $table = 'quote_items';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'quote_id',
        'clinical_plan_item_id',
        'procedure_id',
        'tooth_number',
        'surface',
        'phase',
        'unit_price',
        'quantity',
        'discount_percentage',
        'subtotal',
        'tax',
        'total',
        'is_approved',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
            'phase' => 'integer',
            'unit_price' => 'float',
            'quantity' => 'integer',
            'discount_percentage' => 'float',
            'subtotal' => 'float',
            'tax' => 'float',
            'total' => 'float',
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Quote relation.
     *
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class, 'quote_id');
    }

    /** @return BelongsTo<ClinicalPlanItem, $this> */
    public function clinicalPlanItem(): BelongsTo
    {
        return $this->belongsTo(ClinicalPlanItem::class);
    }

    /**
     * Procedure relation.
     *
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }
}
