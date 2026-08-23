<?php

use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);
});

test('landlord database schema has tenants and tenant_domains tables', function () {
    expect(Schema::connection('landlord')->hasTable('tenants'))->toBeTrue()
        ->and(Schema::connection('landlord')->hasTable('tenant_domains'))->toBeTrue();
});

test('tenant model can be created and queried on landlord connection', function () {
    $tenant = Tenant::create([
        'name' => 'Clínica Dental Sonrisas',
        'slug' => 'sonrisas',
        'database_name' => 'tenant_sonrisas_db',
        'database_password' => 'secret-db-password-123',
        'status' => 'active',
        'settings' => [
            'currency' => 'USD',
            'timezone' => 'America/Santo_Domingo',
        ],
    ]);

    expect($tenant->id)->toBeString()
        ->and($tenant->name)->toBe('Clínica Dental Sonrisas')
        ->and($tenant->slug)->toBe('sonrisas')
        ->and($tenant->database_name)->toBe('tenant_sonrisas_db')
        ->and($tenant->database_password)->toBe('secret-db-password-123')
        ->and($tenant->settings)->toBeArray()
        ->and($tenant->settings['currency'])->toBe('USD')
        ->and($tenant->isActive())->toBeTrue()
        ->and($tenant->isSuspended())->toBeFalse();
});

test('tenant domains relationship works correctly', function () {
    $tenant = Tenant::create([
        'name' => 'Centro Odontológico San Lucas',
        'slug' => 'san-lucas',
        'database_name' => 'tenant_san_lucas_db',
        'status' => 'active',
    ]);

    $primaryDomain = TenantDomain::create([
        'tenant_id' => $tenant->id,
        'domain' => 'sanlucas.bsdental.app',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $customDomain = TenantDomain::create([
        'tenant_id' => $tenant->id,
        'domain' => 'citas.clinicasanlucas.com',
        'is_primary' => false,
        'is_verified' => true,
    ]);

    expect($tenant->domains)->toHaveCount(2)
        ->and($tenant->primaryDomain->id)->toBe($primaryDomain->id)
        ->and($customDomain->tenant->id)->toBe($tenant->id);
});
