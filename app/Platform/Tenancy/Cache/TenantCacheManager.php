<?php

namespace App\Platform\Tenancy\Cache;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

class TenantCacheManager
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Format a key to be isolated by tenant UUID.
     * Format: bsdental:{tenant_uuid}:{key}
     */
    public function formatKey(string $key): string
    {
        $tenant = $this->tenantContext->requireCurrent();

        return "bsdental:{$tenant->id}:{$key}";
    }

    /**
     * Retrieve an item from the tenant cache.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->formatKey($key), $default);
    }

    /**
     * Store an item in the tenant cache.
     */
    public function put(string $key, mixed $value, mixed $ttl = null): bool
    {
        return Cache::put($this->formatKey($key), $value, $ttl);
    }

    /**
     * Remove an item from the tenant cache.
     */
    public function forget(string $key): bool
    {
        return Cache::forget($this->formatKey($key));
    }

    /**
     * Determine if an item exists in the tenant cache.
     */
    public function has(string $key): bool
    {
        return Cache::has($this->formatKey($key));
    }

    /**
     * Flush cached items for a tenant.
     */
    public function flush(?Tenant $tenant = null): void
    {
        $resolvedTenant = $tenant ?? $this->tenantContext->current();
        if ($resolvedTenant !== null) {
            Cache::forget("bsdental:{$resolvedTenant->id}:metadata");
            Cache::forget("bsdental:{$resolvedTenant->id}:entitlements");
        }
    }
}
