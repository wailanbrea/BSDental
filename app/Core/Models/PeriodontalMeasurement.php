<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodontalMeasurement extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'id', 'periodontal_exam_id', 'tooth_number', 'site', 'probing_depth', 'recession',
        'bleeding', 'plaque', 'suppuration', 'mobility', 'furcation', 'is_implant',
    ];

    protected function casts(): array
    {
        return [
            'tooth_number' => 'integer', 'probing_depth' => 'integer', 'recession' => 'integer',
            'bleeding' => 'boolean', 'plaque' => 'boolean', 'suppuration' => 'boolean',
            'mobility' => 'integer', 'furcation' => 'integer', 'is_implant' => 'boolean',
        ];
    }

    public function exam(): BelongsTo { return $this->belongsTo(PeriodontalExam::class, 'periodontal_exam_id'); }
}
