<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $encounter_id
 * @property string $reason
 * @property array<string, mixed> $amended_content
 * @property string $amended_by_user_id
 * @property Carbon $amended_at
 * @property string $integrity_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property ClinicalEncounter $encounter
 * @property User $amendedBy
 */
class ClinicalAmendment extends Model
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
    protected $table = 'clinical_amendments';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'encounter_id',
        'reason',
        'amended_content',
        'amended_by_user_id',
        'amended_at',
        'integrity_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amended_content' => 'array',
            'amended_at' => 'datetime',
        ];
    }

    /**
     * Encounter relation.
     *
     * @return BelongsTo<ClinicalEncounter, $this>
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    /**
     * User who authored the amendment.
     *
     * @return BelongsTo<User, $this>
     */
    public function amendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'amended_by_user_id');
    }
}
