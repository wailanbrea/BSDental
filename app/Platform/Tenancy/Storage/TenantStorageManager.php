<?php

namespace App\Platform\Tenancy\Storage;

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class TenantStorageManager
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Format a path scoped to the tenant storage directory.
     * Format: tenants/{tenant_uuid}/{relativePath}
     */
    public function path(string $relativePath, ?Tenant $tenant = null): string
    {
        $resolvedTenant = $tenant ?? $this->tenantContext->requireCurrent();
        $cleanPath = ltrim($relativePath, '/\\');

        return "tenants/{$resolvedTenant->id}/{$cleanPath}";
    }

    /**
     * Get the private tenant filesystem disk.
     */
    public function disk(string $disk = 'local'): Filesystem
    {
        return Storage::disk($disk);
    }

    /**
     * Write contents to a tenant-scoped file.
     */
    public function put(string $path, mixed $contents, ?Tenant $tenant = null, string $disk = 'local'): bool
    {
        return Storage::disk($disk)->put($this->path($path, $tenant), $contents);
    }

    /**
     * Read contents from a tenant-scoped file.
     */
    public function get(string $path, ?Tenant $tenant = null, string $disk = 'local'): ?string
    {
        return Storage::disk($disk)->get($this->path($path, $tenant));
    }

    /**
     * Check if a tenant-scoped file exists.
     */
    public function exists(string $path, ?Tenant $tenant = null, string $disk = 'local'): bool
    {
        return Storage::disk($disk)->exists($this->path($path, $tenant));
    }

    /**
     * Delete a tenant-scoped file.
     */
    public function delete(string $path, ?Tenant $tenant = null, string $disk = 'local'): bool
    {
        return Storage::disk($disk)->delete($this->path($path, $tenant));
    }
}
