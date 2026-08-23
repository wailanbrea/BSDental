<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $name
 * @property string|null $code
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_main
 * @property bool $is_active
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Collection<int, Room> $rooms
 * @property Collection<int, Professional> $professionals
 * @property Collection<int, User> $users
 */
class Branch extends Model
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
    protected $table = 'branches';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        'is_main',
        'is_active',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Rooms / Dental chairs inside this branch.
     *
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class, 'branch_id');
    }

    /**
     * Professionals assigned to this branch.
     *
     * @return BelongsToMany<Professional, $this>
     */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class, 'branch_professional');
    }

    /**
     * Users assigned to this branch.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user');
    }
}
