<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $odontogram_id
 * @property string|null $encounter_id
 * @property int $tooth_number
 * @property string $surface
 * @property string $condition
 * @property string $lifecycle_state
 * @property string|null $notes
 * @property string|null $recorded_by_user_id
 * @property Carbon $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Odontogram $odontogram
 * @property ClinicalEncounter|null $encounter
 * @property User|null $recordedBy
 */
class OdontogramEntry extends Model
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
    protected $table = 'odontogram_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'odontogram_id',
        'encounter_id',
        'tooth_number',
        'surface',
        'condition',
        'lifecycle_state',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Odontogram relation.
     *
     * @return BelongsTo<Odontogram, $this>
     */
    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class, 'odontogram_id');
    }

    /**
     * Clinical encounter relation.
     *
     * @return BelongsTo<ClinicalEncounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    /**
     * User who recorded the tooth condition.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
