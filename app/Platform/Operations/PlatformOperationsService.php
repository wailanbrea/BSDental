<?php

namespace App\Platform\Operations;

use App\Platform\Plans\Models\Plan;
use App\Platform\Security\AuditLogger;
use App\Platform\Security\Models\LandlordAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlatformOperationsService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Get global aggregated metrics across platform (zero PHI).
     *
     * @return array{
     *   total_tenants: int,
     *   active_tenants: int,
     *   suspended_tenants: int,
     *   total_plans: int,
     *   total_platform_audits: int
     * }
     */
    public function getGlobalMetrics(): array
    {
        return [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'total_plans' => Plan::count(),
            'total_platform_audits' => LandlordAuditLog::count(),
        ];
    }

    /**
     * Check physical health and schema state for a tenant database.
     *
     * @return array{
     *   db_connected: bool,
     *   error: string|null,
     *   tables_count: int,
     *   backup_status: string
     * }
     */
    public function checkTenantHealth(Tenant $tenant): array
    {
        try {
            return $this->tenantContext->execute($tenant, function () use ($tenant) {
                $tables = DB::connection('tenant')->select('SELECT COUNT(*) as cnt FROM sqlite_master WHERE type="table"');
                $count = ! empty($tables) ? (int) $tables[0]->cnt : 0;

                $hasBackups = Storage::disk('local')->exists("tenants/{$tenant->id}/backups");

                return [
                    'db_connected' => true,
                    'error' => null,
                    'tables_count' => $count,
                    'backup_status' => $hasBackups ? 'healthy' : 'pending_first_backup',
                ];
            });
        } catch (Exception $e) {
            return [
                'db_connected' => false,
                'error' => $e->getMessage(),
                'tables_count' => 0,
                'backup_status' => 'error',
            ];
        }
    }

    /**
     * Trigger isolated private database backup for a tenant.
     */
    public function triggerTenantBackup(Tenant $tenant, ?string $adminUserId = null): string
    {
        $backupDir = "tenants/{$tenant->id}/backups";
        $fileName = 'backup_'.date('Y_m_d_His').'.sqlite';
        $fullPath = "{$backupDir}/{$fileName}";

        Storage::disk('local')->makeDirectory($backupDir);

        if (file_exists($tenant->database_name)) {
            $content = (string) file_get_contents($tenant->database_name);
            Storage::disk('local')->put($fullPath, $content);
        } else {
            Storage::disk('local')->put($fullPath, '-- snapshot empty --');
        }

        $this->auditLogger->logPlatform('tenant.backup_triggered', 'Tenant', $tenant->id, [
            'tenant_name' => $tenant->name,
            'backup_file' => $fileName,
            'triggered_by' => $adminUserId,
        ], $tenant);

        return $fullPath;
    }
}
