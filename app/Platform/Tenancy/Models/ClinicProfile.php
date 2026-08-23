<?php

namespace App\Platform\Tenancy\Models;

use Carbon\Carbon;

/**
 * @property int $id
 * @property string $clinic_name
 * @property string|null $legal_name
 * @property string|null $trade_name
 * @property string|null $tax_id
 * @property string|null $phone
 * @property string|null $email
 * @property string $currency
 * @property string $timezone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ClinicProfile extends TenantModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clinic_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'clinic_name',
        'legal_name',
        'trade_name',
        'tax_id',
        'phone',
        'email',
        'currency',
        'timezone',
    ];
}
