<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalPlanItem extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'clinical_plan_items';

    protected $fillable = [
        'clinical_plan_id',
        'procedure_id',
        'tooth_number',
        'surface',
        'quantity',
        'clinical_note',
        'priority',
        'estimated_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
            'quantity' => 'integer',
            'estimated_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<ClinicalPlan, $this> */
    public function clinicalPlan(): BelongsTo
    {
        return $this->belongsTo(ClinicalPlan::class);
    }

    /** @return BelongsTo<Procedure, $this> */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /** @return HasMany<QuoteItem, $this> */
    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}
