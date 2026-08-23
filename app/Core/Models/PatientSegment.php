<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property array<string, mixed> $criteria_json
 * @property int $patient_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PatientSegment extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $table = 'patient_segments';

    protected $fillable = [
        'id',
        'name',
        'criteria_json',
        'patient_count',
    ];

    protected function casts(): array
    {
        return [
            'criteria_json' => 'array',
            'patient_count' => 'integer',
        ];
    }
}
