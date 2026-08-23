<?php

namespace App\Platform\Actions;

use App\Platform\Tenancy\Cache\TenantCacheManager;
use App\Platform\Tenancy\Models\Tenant;

class ResumeTenantAction
{
    public function __construct(
        protected TenantCacheManager $cacheManager
    ) {}

    /**
     * Resume a suspended tenant organization.
     */
    public function execute(Tenant $tenant): Tenant
    {
        $settings = $tenant->settings ?? [];
        unset($settings['suspension_reason'], $settings['suspended_at']);

        $tenant->update([
            'status' => 'active',
            'settings' => $settings,
        ]);

        $this->cacheManager->flush($tenant);

        return $tenant;
    }
}
