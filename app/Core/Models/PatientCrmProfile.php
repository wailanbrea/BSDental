<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $stage_id
 * @property string|null $source
 * @property float $estimated_lifetime_value
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property CrmStage $stage
 */
class PatientCrmProfile extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'patient_crm_profiles';

    protected $fillable = [
        'id',
        'patient_id',
        'stage_id',
        'source',
        'estimated_lifetime_value',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_lifetime_value' => 'float',
        ];
    }

    /**
     * Patient relation.
     *
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Stage relation.
     *
     * @return BelongsTo<CrmStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class, 'stage_id');
    }
}
