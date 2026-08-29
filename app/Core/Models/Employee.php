<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string|null $professional_id
 * @property string $compensation_type
 * @property float $monthly_salary
 * @property float $commission_rate
 * @property Carbon|null $hire_date
 * @property Professional|null $professional
 */
class Employee extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $fillable = [
        'employee_number',
        'professional_id',
        'full_name',
        'position',
        'compensation_type',
        'monthly_salary',
        'commission_rate',
        'hire_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_salary' => 'float',
            'commission_rate' => 'float',
            'hire_date' => 'date',
        ];
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return HasMany<PayrollItem, $this> */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
