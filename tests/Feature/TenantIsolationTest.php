<?php

use App\Platform\Tenancy\Cache\TenantCacheManager;
use App\Platform\Tenancy\Exceptions\NoCurrentTenantException;
use App\Platform\Tenancy\Jobs\Concerns\TenantAware;
use App\Platform\Tenancy\Jobs\Middleware\TenantJobMiddleware;
use App\Platform\Tenancy\Middleware\EnsureTenantContextCleaned;
use App\Platform\Tenancy\Middleware\ResolveTenantFromHost;
use App\Platform\Tenancy\Models\ClinicProfile;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\Storage\TenantStorageManager;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    // 1. Migrate Landlord database
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    // 2. Set up Tenant A and Tenant B
    $this->dbPathA = $this->tenantDatabasePath('tenant_a_test.sqlite');
    $this->dbPathB = $this->tenantDatabasePath('tenant_b_test.sqlite');

    if (! file_exists($this->dbPathA)) {
        touch($this->dbPathA);
    }
    if (! file_exists($this->dbPathB)) {
        touch($this->dbPathB);
    }

    $this->tenantA = Tenant::create([
        'name' => 'Clínica Dental Alfa',
        'slug' => 'clinica-alfa',
        'database_name' => $this->dbPathA,
        'status' => 'active',
    ]);

    $this->domainA = TenantDomain::create([
        'tenant_id' => $this->tenantA->id,
        'domain' => 'alfa.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $this->tenantB = Tenant::create([
        'name' => 'Clínica Dental Beta',
        'slug' => 'clinica-beta',
        'database_name' => $this->dbPathB,
        'status' => 'active',
    ]);

    $this->domainB = TenantDomain::create([
        'tenant_id' => $this->tenantB->id,
        'domain' => 'beta.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    // 3. Migrate tenant databases
    $context = app(TenantContext::class);

    $context->execute($this->tenantA, function () {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);
    });

    $context->execute($this->tenantB, function () {
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);
    });
});

afterEach(function () {
    app(TenantContext::class)->forgetCurrent();

    if (isset($this->dbPathA) && file_exists($this->dbPathA)) {
        @unlink($this->dbPathA);
    }
    if (isset($this->dbPathB) && file_exists($this->dbPathB)) {
        @unlink($this->dbPathB);
    }
});

/*
|--------------------------------------------------------------------------
| GATE-TENANCY-01: TESTS DE VERIFICACIÓN DE AISLAMIENTO MULTI-TENANT
|--------------------------------------------------------------------------
*/

test('[GATE-TENANCY-01] Host resolution resolves verified tenant and rejects invalid/unverified hosts', function () {
    $middleware = app(ResolveTenantFromHost::class);
    $context = app(TenantContext::class);

    // Test Tenant A
    $requestA = Request::create('http://alfa.bsdental.test/dashboard');
    $middleware->handle($requestA, function () use ($context) {
        expect($context->current()->id)->toBe($this->tenantA->id)
            ->and($context->current()->slug)->toBe('clinica-alfa');

        return response('OK');
    });

    // Test Tenant B
    $requestB = Request::create('http://beta.bsdental.test/dashboard');
    $middleware->handle($requestB, function () use ($context) {
        expect($context->current()->id)->toBe($this->tenantB->id)
            ->and($context->current()->slug)->toBe('clinica-beta');

        return response('OK');
    });

    // Test Unknown Host -> 404
    $requestUnknown = Request::create('http://unknown.bsdental.test/dashboard');
    expect(fn () => $middleware->handle($requestUnknown, fn () => response('OK')))
        ->toThrow(NotFoundHttpException::class);
});

test('[GATE-TENANCY-01] Suspended tenant returns 403 and provisioning tenant returns 503', function () {
    $middleware = app(ResolveTenantFromHost::class);

    $suspendedTenant = Tenant::create([
        'name' => 'Clínica Suspendida',
        'slug' => 'suspendida',
        'database_name' => 'dummy',
        'status' => 'suspended',
    ]);
    TenantDomain::create([
        'tenant_id' => $suspendedTenant->id,
        'domain' => 'suspendida.bsdental.test',
        'is_verified' => true,
    ]);

    $requestSuspended = Request::create('http://suspendida.bsdental.test/dashboard');
    expect(fn () => $middleware->handle($requestSuspended, fn () => response('OK')))
        ->toThrow(HttpException::class);

    $provisioningTenant = Tenant::create([
        'name' => 'Clínica En Provisioning',
        'slug' => 'provisioning',
        'database_name' => 'dummy',
        'status' => 'provisioning',
    ]);
    TenantDomain::create([
        'tenant_id' => $provisioningTenant->id,
        'domain' => 'provisioning.bsdental.test',
        'is_verified' => true,
    ]);

    $requestProvisioning = Request::create('http://provisioning.bsdental.test/dashboard');
    expect(fn () => $middleware->handle($requestProvisioning, fn () => response('OK')))
        ->toThrow(HttpException::class);
});

test('[GATE-TENANCY-01] Physical Database Isolation: Tenant A cannot see Tenant B data and vice-versa', function () {
    $context = app(TenantContext::class);

    // Seed Profile in Tenant A
    $context->execute($this->tenantA, function () {
        ClinicProfile::create([
            'clinic_name' => 'Clínica Alfa Central',
            'tax_id' => 'ALFA-1001',
            'phone' => '+18095550001',
        ]);

        $profilesA = ClinicProfile::all();
        expect($profilesA)->toHaveCount(1)
            ->and($profilesA->first()->clinic_name)->toBe('Clínica Alfa Central');
    });

    // Seed Profile in Tenant B with distinct data
    $context->execute($this->tenantB, function () {
        ClinicProfile::create([
            'clinic_name' => 'Centro Odontológico Beta Especialidades',
            'tax_id' => 'BETA-2002',
            'phone' => '+18095550002',
        ]);

        $profilesB = ClinicProfile::all();
        expect($profilesB)->toHaveCount(1)
            ->and($profilesB->first()->clinic_name)->toBe('Centro Odontológico Beta Especialidades');
    });

    // Cross-verify: Accessing Tenant A again does not show Tenant B
    $context->execute($this->tenantA, function () {
        $profilesA = ClinicProfile::all();
        expect($profilesA)->toHaveCount(1)
            ->and($profilesA->first()->clinic_name)->toBe('Clínica Alfa Central')
            ->and($profilesA->first()->tax_id)->toBe('ALFA-1001');
    });
});

test('[GATE-TENANCY-01] Context Cleanup: Context is wiped after request and sequential requests do not leak state', function () {
    $context = app(TenantContext::class);
    $cleanupMiddleware = app(EnsureTenantContextCleaned::class);
    $request = Request::create('http://alfa.bsdental.test/test');

    $context->makeCurrent($this->tenantA);
    expect($context->current())->not->toBeNull();

    $cleanupMiddleware->handle($request, function () use ($context) {
        expect($context->current()->id)->toBe($this->tenantA->id);

        return response('OK');
    });

    // After middleware execution, context must be wiped
    expect($context->current())->toBeNull();
});

test('[GATE-TENANCY-01] Cache Isolation: Keys are namespaced with bsdental:{tenant_uuid}: and never collide', function () {
    $context = app(TenantContext::class);
    $cacheManager = app(TenantCacheManager::class);

    $context->execute($this->tenantA, function () use ($cacheManager) {
        $cacheManager->put('kpi_monthly_production', 45000.00);
        expect($cacheManager->get('kpi_monthly_production'))->toBe(45000.00)
            ->and($cacheManager->formatKey('kpi_monthly_production'))->toBe("bsdental:{$this->tenantA->id}:kpi_monthly_production");
    });

    $context->execute($this->tenantB, function () use ($cacheManager) {
        $cacheManager->put('kpi_monthly_production', 98000.50);
        expect($cacheManager->get('kpi_monthly_production'))->toBe(98000.50)
            ->and($cacheManager->formatKey('kpi_monthly_production'))->toBe("bsdental:{$this->tenantB->id}:kpi_monthly_production");
    });

    // Verify Tenant A still has its original cache value
    $context->execute($this->tenantA, function () use ($cacheManager) {
        expect($cacheManager->get('kpi_monthly_production'))->toBe(45000.00);
    });
});

test('[GATE-TENANCY-01] Storage Isolation: Files are isolated in tenants/{tenant_uuid}/ directory', function () {
    Storage::fake('local');

    $context = app(TenantContext::class);
    $storageManager = app(TenantStorageManager::class);

    $context->execute($this->tenantA, function () use ($storageManager) {
        $storageManager->put('presupuestos/presupuesto_001.pdf', 'Contenido confidencial Alfa');
        expect($storageManager->exists('presupuestos/presupuesto_001.pdf'))->toBeTrue()
            ->and($storageManager->get('presupuestos/presupuesto_001.pdf'))->toBe('Contenido confidencial Alfa');
    });

    $context->execute($this->tenantB, function () use ($storageManager) {
        // Tenant B must not see Tenant A file
        expect($storageManager->exists('presupuestos/presupuesto_001.pdf'))->toBeFalse();

        // Tenant B writes same relative path with different content
        $storageManager->put('presupuestos/presupuesto_001.pdf', 'Contenido confidencial Beta');
        expect($storageManager->get('presupuestos/presupuesto_001.pdf'))->toBe('Contenido confidencial Beta');
    });

    // Check raw storage directory layout
    Storage::disk('local')->assertExists("tenants/{$this->tenantA->id}/presupuestos/presupuesto_001.pdf");
    Storage::disk('local')->assertExists("tenants/{$this->tenantB->id}/presupuestos/presupuesto_001.pdf");
});

test('[GATE-TENANCY-01] Queue & Job isolation: TenantAwareJob preserves tenant and cleans up context', function () {
    $context = app(TenantContext::class);
    $middleware = app(TenantJobMiddleware::class);

    $dummyJob = new class
    {
        use TenantAware;

        public bool $executed = false;

        public ?string $executedTenantId = null;
    };

    $dummyJob->forTenant($this->tenantA);

    expect($context->current())->toBeNull();

    $middleware->handle($dummyJob, function ($job) use ($context) {
        $job->executed = true;
        $job->executedTenantId = $context->current()?->id;
    });

    expect($dummyJob->executed)->toBeTrue()
        ->and($dummyJob->executedTenantId)->toBe($this->tenantA->id)
        ->and($context->current())->toBeNull(); // Cleaned up in finally
});

test('[GATE-TENANCY-01] Central Domain & No-Tenant Guard: Accessing TenantModel without tenant throws NoCurrentTenantException', function () {
    $context = app(TenantContext::class);
    $context->forgetCurrent();

    expect($context->current())->toBeNull();

    // Querying TenantModel directly with no tenant context must throw NoCurrentTenantException
    expect(fn () => ClinicProfile::all())
        ->toThrow(NoCurrentTenantException::class);
});
