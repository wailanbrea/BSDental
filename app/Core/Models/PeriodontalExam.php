<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodontalExam extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = ['id', 'odontogram_id', 'encounter_id', 'status', 'notes', 'recorded_by_user_id', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    /** @return BelongsTo<Odontogram, $this> */
    public function odontogram(): BelongsTo
    {
        return $this->belongsTo(Odontogram::class);
    }

    /** @return BelongsTo<ClinicalEncounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return HasMany<PeriodontalMeasurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(PeriodontalMeasurement::class);
    }
}
