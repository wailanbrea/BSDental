<?php

namespace App\Platform\Tenancy\Exceptions;

use RuntimeException;

class TenantNotFoundException extends RuntimeException
{
    public static function forHost(string $host): self
    {
        return new self('No verified tenant found for host: {System.Management.Automation.Internal.Host.InternalHost}.');
    }

    public static function forId(string $id): self
    {
        return new self('Tenant with ID {} was not found.');
    }
}
