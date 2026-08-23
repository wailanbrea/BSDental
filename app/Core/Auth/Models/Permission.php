<?php

namespace App\Core\Auth\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'tenant';
}
