<?php

use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Security\AuditLogger;
use App\Platform\Security\Uploads\SecureUploadService;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    Storage::fake('local');

    $this->dbPathSec = $this->tenantDatabasePath('tenant_gate_sec_test.sqlite');
    if (! file_exists($this->dbPathSec)) {
        touch($this->dbPathSec);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental Seguridad Gate',
        'slug' => 'seguridad-gate',
        'database_name' => $this->dbPathSec,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'seguridad.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);
    $context->execute($this->tenant, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);
    });
});

test('[GATE SEC] Holistic verification of ASVS 5.0 Level 2 baseline security controls', function () {
    // 1. Browser Security Headers & CSP Nonce
    $response = $this->get('http://seguridad.bsdental.test/login');
    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
    expect($response->headers->get('Content-Security-Policy'))->toContain('nonce-');

    // 2. Audit Logging & Strict Redaction of Sensitive Fields
    $admin = PlatformUser::create([
        'name' => 'Security Officer',
        'email' => 'secops@bsdental.app',
        'password' => Hash::make('AdminSecret123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);
    $this->actingAs($admin, 'platform');

    $auditLogger = app(AuditLogger::class);
    $log = $auditLogger->logPlatform('system.security_check', 'Tenant', $this->tenant->id, [
        'check' => 'ASVS Level 2 Verification',
        'password' => 'PlaintextPasswordToBeRedacted',
        'token' => 'jwt_secret_token_to_redact',
        'two_factor_secret' => 'SECRETKEY123456',
        'credit_card' => '4532012345678901',
    ], $this->tenant);

    expect($log->metadata['check'])->toBe('ASVS Level 2 Verification')
        ->and($log->metadata['password'])->toBe('[REDACTED]')
        ->and($log->metadata['token'])->toBe('[REDACTED]')
        ->and($log->metadata['two_factor_secret'])->toBe('[REDACTED]')
        ->and($log->metadata['credit_card'])->toBe('[REDACTED]');

    // 3. Secure File Upload Pipeline with Magic Bytes & UUID randomization
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $uploadService = app(SecureUploadService::class);
    $validImage = UploadedFile::fake()->image('radiografia.png', 400, 400);
    $uploadResult = $uploadService->store($validImage, 'clinical_scans');

    expect($uploadResult['mime_type'])->toBe('image/png')
        ->and($uploadResult['filename'])->toMatch('/^[0-9a-f\-]{36}\.png$/');
    Storage::disk('local')->assertExists("tenants/{$this->tenant->id}/uploads/clinical_scans/{$uploadResult['filename']}");

    // 4. Executable / Malicious script upload rejection
    $script = UploadedFile::fake()->createWithContent('exploit.php', '<?php system($_GET["c"]); ?>');
    expect(fn () => $uploadService->store($script, 'clinical_scans'))
        ->toThrow(InvalidArgumentException::class);
});
