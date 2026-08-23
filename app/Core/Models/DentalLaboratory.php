<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Collection<int, LabOrder> $orders
 */
class DentalLaboratory extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'dental_laboratories';

    protected $fillable = [
        'id',
        'name',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Orders relation.
     *
     * @return HasMany<LabOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(LabOrder::class, 'laboratory_id');
    }
}
