<?php

namespace App\Platform\Tenancy\Listeners;

use App\Platform\Tenancy\TenantContext;

class ResetTenantContextOnRequestTerminated
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle the terminated event ensuring zero memory leakage in Octane / FrankenPHP.
     */
    public function handle(mixed $event = null): void
    {
        $this->tenantContext->forgetCurrent();
    }
}
