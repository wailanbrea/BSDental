<?php

namespace App\Core\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $slug
 * @property string $title
 * @property int $version
 * @property string $content
 * @property bool $required_witness
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class ConsentTemplate extends Model
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
    protected $table = 'consent_templates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'slug',
        'title',
        'version',
        'content',
        'required_witness',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'required_witness' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
