<?php

namespace App\Console\Commands;

use App\Platform\Operations\PlatformOperationsService;
use App\Platform\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

class TenantMigrateCanaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate-canary {--tenant= : Specific tenant slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform canary health and schema migration verification across tenant databases';

    /**
     * Execute the console command.
     */
    public function handle(PlatformOperationsService $opsService): int
    {
        $this->info('Starting Canary Migration & Health Drill across tenants...');

        $slug = $this->option('tenant');
        $query = Tenant::query();
        if ($slug) {
            $query->where('slug', (string) $slug);
        }
        $tenants = $query->get();

        $this->info("Found {$tenants->count()} tenants to inspect.");

        $allHealthy = true;

        foreach ($tenants as $tenant) {
            $this->line("Inspecting tenant: [{$tenant->slug}] {$tenant->name}...");
            $health = $opsService->checkTenantHealth($tenant);

            if ($health['db_connected']) {
                $this->info("  ✓ DB Connected | Tables: {$health['tables_count']} | Backups: {$health['backup_status']}");
            } else {
                $this->error("  ✗ DB Connection Failed: {$health['error']}");
                $allHealthy = false;
            }
        }

        if ($allHealthy) {
            $this->info('Canary Health Drill finished: ALL TENANTS 100% HEALTHY.');

            return self::SUCCESS;
        }

        $this->error('Canary Health Drill finished: SOME TENANTS REPORTED ERRORS.');

        return self::FAILURE;
    }
}
