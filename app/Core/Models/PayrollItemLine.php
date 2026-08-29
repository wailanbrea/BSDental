<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemLine extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'payroll_item_id',
        'professional_compensation_id',
        'type',
        'description',
        'amount',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }

    /** @return BelongsTo<PayrollItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    /** @return BelongsTo<ProfessionalCompensation, $this> */
    public function compensation(): BelongsTo
    {
        return $this->belongsTo(ProfessionalCompensation::class, 'professional_compensation_id');
    }
}
