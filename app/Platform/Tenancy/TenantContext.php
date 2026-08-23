<?php

namespace App\Platform\Tenancy;

use App\Platform\Tenancy\Exceptions\NoCurrentTenantException;
use App\Platform\Tenancy\Models\Tenant;

class TenantContext
{
    /**
     * Get the currently active tenant, or null if not set.
     */
    public function current(): ?Tenant
    {
        return Tenant::current();
    }

    /**
     * Determine if there is an active tenant context.
     */
    public function check(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Get the currently active tenant or throw an exception.
     *
     * @throws NoCurrentTenantException
     */
    public function requireCurrent(): Tenant
    {
        $tenant = $this->current();

        if ($tenant === null) {
            throw NoCurrentTenantException::make();
        }

        return $tenant;
    }

    /**
     * Set the given tenant as current.
     */
    public function makeCurrent(Tenant $tenant): void
    {
        $tenant->makeCurrent();
    }

    /**
     * Clear the current tenant context.
     */
    public function forgetCurrent(): void
    {
        Tenant::forgetCurrent();
    }

    /**
     * Execute a callback in the context of the given tenant and restore previous context.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function execute(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->current();

        $this->makeCurrent($tenant);

        try {
            return $callback($tenant);
        } finally {
            if ($previousTenant !== null) {
                $this->makeCurrent($previousTenant);
            } else {
                $this->forgetCurrent();
            }
        }
    }
}
