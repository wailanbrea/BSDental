<?php

use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Security\AuditLogger;
use App\Platform\Security\Models\LandlordAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPath = database_path('tenant_audit_test.sqlite');
    if (! file_exists($this->dbPath)) {
        touch($this->dbPath);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Auditoría',
        'slug' => 'clinica-auditoria',
        'database_name' => $this->dbPath,
        'status' => 'active',
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

test('audit logger records platform event on landlord db and redacts sensitive parameters', function () {
    $admin = PlatformUser::create([
        'name' => 'Auditor',
        'email' => 'auditor@bsdental.app',
        'password' => Hash::make('Secret123!'),
        'role' => 'superadmin',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'platform');

    $logger = app(AuditLogger::class);
    $entry = $logger->logPlatform('tenant.created', 'Tenant', $this->tenant->id, [
        'name' => 'Clínica Auditoría',
        'password' => 'SuperSecretPlainPassword',
        'token' => 'jwt_secret_token_1234',
        'plan' => 'pro',
    ], $this->tenant);

    expect($entry)->toBeInstanceOf(LandlordAuditLog::class)
        ->and($entry->action)->toBe('tenant.created')
        ->and($entry->platform_user_id)->toBe($admin->id)
        ->and($entry->metadata['name'])->toBe('Clínica Auditoría')
        ->and($entry->metadata['password'])->toBe('[REDACTED]')
        ->and($entry->metadata['token'])->toBe('[REDACTED]')
        ->and($entry->metadata['plan'])->toBe('pro');

    expect(LandlordAuditLog::count())->toBe(1);
});

test('audit logger records tenant event on tenant db with sanitization', function () {
    $context = app(TenantContext::class);

    $context->execute($this->tenant, function () {
        $logger = app(AuditLogger::class);

        $entry = $logger->logTenant('payment.refunded', 'Payment', 'pay-uuid-123', [
            'amount' => 150.00,
            'reason' => 'Duplicate charge',
            'credit_card' => '4111222233334444',
        ]);

        expect($entry)->toBeInstanceOf(TenantAuditLog::class)
            ->and($entry->action)->toBe('payment.refunded')
            ->and($entry->metadata['amount'])->toEqual(150.00)
            ->and($entry->metadata['credit_card'])->toBe('[REDACTED]');

        expect(TenantAuditLog::count())->toBe(1);
    });
});
