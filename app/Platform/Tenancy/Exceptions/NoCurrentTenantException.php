<?php

namespace App\Platform\Tenancy\Exceptions;

use RuntimeException;

class NoCurrentTenantException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The requested operation requires an active tenant context, but no tenant is currently set.');
    }
}
