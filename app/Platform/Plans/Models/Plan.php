<?php

namespace App\Platform\Plans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property list<string> $modules
 * @property int $max_users
 * @property int $max_branches
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    use HasUuids;

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'landlord';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'plans';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'description',
        'modules',
        'max_users',
        'max_branches',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'max_users' => 'integer',
            'max_branches' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Determine if plan includes a specific module.
     */
    public function hasModule(string $module): bool
    {
        return in_array($module, $this->modules, true);
    }
}
