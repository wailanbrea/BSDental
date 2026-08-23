<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $name
 * @property string $channel
 * @property string|null $segment_id
 * @property string $message_body
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property int $sent_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property PatientSegment|null $segment
 */
class MarketingCampaign extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'id',
        'name',
        'channel',
        'segment_id',
        'message_body',
        'status',
        'scheduled_at',
        'sent_count',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'sent_count' => 'integer',
        ];
    }

    /**
     * Segment relation.
     *
     * @return BelongsTo<PatientSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(PatientSegment::class, 'segment_id');
    }
}
