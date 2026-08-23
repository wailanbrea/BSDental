<?php

namespace App\Platform\Actions;

use App\Platform\Tenancy\Cache\TenantCacheManager;
use App\Platform\Tenancy\Models\Tenant;

class SuspendTenantAction
{
    public function __construct(
        protected TenantCacheManager $cacheManager
    ) {}

    /**
     * Suspend a tenant organization.
     */
    public function execute(Tenant $tenant, string $reason = 'Suspended by platform administrator'): Tenant
    {
        $settings = $tenant->settings ?? [];
        $settings['suspension_reason'] = $reason;
        $settings['suspended_at'] = now()->toISOString();

        $tenant->update([
            'status' => 'suspended',
            'settings' => $settings,
        ]);

        $this->cacheManager->flush($tenant);

        return $tenant;
    }
}
