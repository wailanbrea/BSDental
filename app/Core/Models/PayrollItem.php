<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'fixed_salary_amount',
        'commission_amount',
        'net_amount',
        'status',
        'employee_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'fixed_salary_amount' => 'float',
            'commission_amount' => 'float',
            'net_amount' => 'float',
            'employee_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<PayrollItemLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class);
    }
}
