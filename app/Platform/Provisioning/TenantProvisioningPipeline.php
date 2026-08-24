<?php

namespace App\Platform\Provisioning;

use App\Core\Auth\Database\Seeders\TenantRbacSeeder;
use App\Core\Auth\Models\User;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\Storage\TenantStorageManager;
use App\Platform\Tenancy\TenantContext;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class TenantProvisioningPipeline
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected TenantStorageManager $storageManager
    ) {}

    /**
     * Execute full automated provisioning pipeline for a new tenant.
     *
     * @param  array{
     *     name: string,
     *     slug: string,
     *     domain: string,
     *     plan_id: string,
     *     owner_name: string,
     *     owner_email: string,
     *     owner_password: string,
     *     currency?: string,
     *     timezone?: string,
     *     database_name?: string
     * }  $input
     *
     * @throws Exception
     */
    public function run(array $input): Tenant
    {
        // 1. Validate input
        $this->validateInput($input);

        $slug = Str::slug($input['slug']);
        $domainHost = strtolower(trim($input['domain']));
        $dbPath = $input['database_name'] ?? $this->defaultDatabasePath($slug);

        // 2. Create Landlord Tenant record in 'provisioning' state
        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'name' => trim($input['name']),
            'slug' => $slug,
            'database_name' => $dbPath,
            'plan_id' => $input['plan_id'],
            'status' => 'provisioning',
            'settings' => [
                'currency' => $input['currency'] ?? 'USD',
                'timezone' => $input['timezone'] ?? 'UTC',
            ],
        ]);

        try {
            // 3. Reserve and map primary domain
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'domain' => $domainHost,
                'is_primary' => true,
                'is_verified' => true,
            ]);

            // 4. Provision Physical Database File/Schema
            $this->provisionDatabase($dbPath);

            // 5 & 6. Migrate Tenant Database
            $this->tenantContext->execute($tenant, function () use ($input, $tenant) {
                Schema::connection('tenant')->dropAllTables();
                Artisan::call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--database' => 'tenant',
                    '--realpath' => false,
                    '--force' => true,
                ]);

                // 7. Seed Clinic Profile
                ClinicProfile::create([
                    'clinic_name' => $tenant->name,
                    'legal_name' => $tenant->name,
                    'trade_name' => $tenant->name,
                    'email' => $input['owner_email'],
                    'currency' => $input['currency'] ?? 'USD',
                    'timezone' => $input['timezone'] ?? 'UTC',
                ]);

                // 8. Seed RBAC Roles and Permissions
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $seeder = new TenantRbacSeeder;
                $seeder->run();

                // 9. Create Tenant Owner User
                /** @var User $owner */
                $owner = User::create([
                    'name' => $input['owner_name'],
                    'email' => strtolower(trim($input['owner_email'])),
                    'password' => Hash::make($input['owner_password']),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]);

                $owner->assignRole('Owner');
            });

            // 10. Initialize Private Storage
            $this->storageManager->put('system/init.json', json_encode([
                'provisioned_at' => now()->toISOString(),
                'tenant_id' => $tenant->id,
            ], JSON_THROW_ON_ERROR), $tenant);

            // 11. Health Check
            $this->tenantContext->execute($tenant, function () {
                $userCount = User::count();
                if ($userCount < 1) {
                    throw new Exception('Owner user was not seeded properly.');
                }
            });

            // 12. Activate Tenant
            $tenant->update(['status' => 'active']);

            return $tenant->fresh(['domains', 'primaryDomain', 'plan']) ?? $tenant;
        } catch (Exception $e) {
            $tenant->update(['status' => 'provisioning_failed']);
            throw $e;
        }
    }

    protected function defaultDatabasePath(string $slug): string
    {
        if (! app()->environment('testing')) {
            return database_path("tenant_{$slug}.sqlite");
        }

        $directory = config('database.testing_tenant_directory');
        if (! is_string($directory) || $directory === '') {
            throw new RuntimeException('Tenant provisioning requires an isolated database directory while testing.');
        }

        return rtrim($directory, '\\/').DIRECTORY_SEPARATOR."tenant_{$slug}.sqlite";
    }

    /**
     * Validate input payload before running pipeline.
     *
     * @param  array<string, mixed>  $input
     */
    protected function validateInput(array $input): void
    {
        $required = ['name', 'slug', 'domain', 'plan_id', 'owner_name', 'owner_email', 'owner_password'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                throw new InvalidArgumentException("El campo '{$field}' es obligatorio para aprovisionar un tenant.");
            }
        }

        $slug = Str::slug($input['slug']);
        if (Tenant::where('slug', $slug)->exists()) {
            throw new InvalidArgumentException("El slug '{$slug}' ya se encuentra registrado por otra organización.");
        }

        $domain = strtolower(trim($input['domain']));
        if (TenantDomain::where('domain', $domain)->exists()) {
            throw new InvalidArgumentException("El dominio '{$domain}' ya se encuentra asignado.");
        }

        if (! Plan::where('id', $input['plan_id'])->exists()) {
            throw new InvalidArgumentException("El plan comercial '{$input['plan_id']}' no existe en el sistema central.");
        }
    }

    /**
     * Provision physical database target.
     */
    protected function provisionDatabase(string $dbPath): void
    {
        if (! file_exists($dbPath)) {
            $dir = dirname($dbPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            touch($dbPath);
        }
    }
}
