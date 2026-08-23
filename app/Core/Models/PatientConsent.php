<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $consent_template_id
 * @property int $template_version
 * @property string $title
 * @property string $rendered_content
 * @property string $signed_by_name
 * @property string|null $signed_by_identification
 * @property string $relationship
 * @property string $signature_type
 * @property string $signature_data
 * @property Carbon $signed_at
 * @property string|null $signed_ip
 * @property string $integrity_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Patient $patient
 * @property ConsentTemplate $template
 */
class PatientConsent extends Model
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
    protected $table = 'patient_consents';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'consent_template_id',
        'template_version',
        'title',
        'rendered_content',
        'signed_by_name',
        'signed_by_identification',
        'relationship',
        'signature_type',
        'signature_data',
        'signed_at',
        'signed_ip',
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
            'template_version' => 'integer',
            'signed_at' => 'datetime',
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
     * Template relation.
     *
     * @return BelongsTo<ConsentTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class, 'consent_template_id');
    }
}
