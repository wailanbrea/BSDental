<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'surfaces',
        'condition',
        'entry_type',
        'code_system',
        'clinical_code',
        'clinical_display',
        'clinical_status',
        'verification_status',
        'procedure_id',
        'supersedes_entry_id',
        'amendment_reason',
        'device_details',
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
            'surfaces' => 'array',
            'device_details' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Normalize condition values written before the clinical vocabulary was finalized.
     */
    public function getConditionAttribute(string $value): string
    {
        return match ($value) {
            'resin', 'restoration' => 'restored_composite',
            'amalgam' => 'restored_amalgam',
            'absent' => 'missing',
            'root_canal' => 'endodontic',
            default => $value,
        };
    }

    /**
     * Normalize legacy surface labels without rewriting immutable history.
     */
    public function getSurfaceAttribute(string $value): string
    {
        return match ($value) {
            'occlusal', 'incisal' => 'occlusal_incisal',
            'lingual', 'palatal' => 'lingual_palatal',
            default => $value,
        };
    }

    /**
     * Normalize every legacy label in structured surface arrays on read.
     *
     * @return list<string>
     */
    public function getSurfacesAttribute(mixed $value): array
    {
        $surfaces = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($surfaces) || $surfaces === []) {
            $surfaces = [$this->attributes['surface'] ?? 'all'];
        }

        return array_values(array_unique(array_map(fn (string $surface): string => match ($surface) {
            'occlusal', 'incisal' => 'occlusal_incisal',
            'lingual', 'palatal' => 'lingual_palatal',
            default => $surface,
        }, $surfaces)));
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

    /** @return BelongsTo<self, $this> */
    public function supersededEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_entry_id');
    }

    /** @return HasMany<self, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'supersedes_entry_id');
    }
}
