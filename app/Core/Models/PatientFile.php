<?php

namespace App\Core\Models;

use App\Core\Auth\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $patient_id
 * @property string $category
 * @property string $filename
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $stored_path
 * @property string|null $notes
 * @property string|null $uploaded_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Patient $patient
 * @property User|null $uploader
 */
class PatientFile extends Model
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
    protected $table = 'patient_files';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'patient_id',
        'category',
        'filename',
        'original_name',
        'mime_type',
        'size_bytes',
        'stored_path',
        'notes',
        'tooth_number',
        'odontogram_entry_id',
        'encounter_id',
        'taken_at',
        'metadata',
        'uploaded_by_user_id',
    ];

    /**
     * Private storage details must never be serialized to the browser.
     *
     * @var list<string>
     */
    protected $hidden = [
        'filename',
        'stored_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'tooth_number' => 'integer',
            'taken_at' => 'datetime',
            'metadata' => 'array',
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
     * User who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function odontogramEntry(): BelongsTo
    {
        return $this->belongsTo(OdontogramEntry::class, 'odontogram_entry_id');
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }
}
