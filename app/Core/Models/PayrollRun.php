<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'run_number',
        'period_start',
        'period_end',
        'status',
        'fixed_salary_total',
        'commission_total',
        'net_total',
        'paid_at',
        'created_by_user_id',
        'paid_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'fixed_salary_total' => 'float',
            'commission_total' => 'float',
            'net_total' => 'float',
            'paid_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
