<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $channel
 * @property string $trigger_event
 * @property string $name
 * @property string $body_template
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationTemplate extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'notification_templates';

    protected $fillable = [
        'id',
        'channel',
        'trigger_event',
        'name',
        'body_template',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
