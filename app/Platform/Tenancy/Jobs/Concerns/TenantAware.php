<?php

namespace App\Platform\Tenancy\Jobs\Concerns;

use App\Platform\Tenancy\Models\Tenant;

trait TenantAware
{
    public ?string $tenantId = null;

    /**
     * Bind tenant to the job.
     */
    public function forTenant(Tenant|string $tenant): static
    {
        $this->tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this;
    }

    /**
     * Get the tenant ID for this job.
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }
}
