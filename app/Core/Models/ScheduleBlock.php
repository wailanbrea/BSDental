<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $branch_id
 * @property string|null $professional_id
 * @property string|null $room_id
 * @property string $title
 * @property string $reason
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property string|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Branch $branch
 * @property Professional|null $professional
 * @property Room|null $room
 */
class ScheduleBlock extends Model
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
    protected $table = 'schedule_blocks';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'branch_id',
        'professional_id',
        'room_id',
        'title',
        'reason',
        'start_time',
        'end_time',
        'created_by_user_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * Branch relation.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Professional relation.
     *
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    /**
     * Room relation.
     *
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
