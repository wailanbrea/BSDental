<?php

use App\Platform\Security\Uploads\SecureUploadService;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental Imagenología',
        'slug' => 'imagen-dental',
        'database_name' => $this->tenantDatabasePath('tenant_upload_test.sqlite'),
        'status' => 'active',
    ]);

    $this->context = app(TenantContext::class);
    $this->context->makeCurrent($this->tenant);
    $this->service = app(SecureUploadService::class);
});

test('secure upload service stores valid image with randomized uuid in private tenant storage', function () {
    $file = UploadedFile::fake()->image('radiografia_panoramica.png', 800, 600);

    $result = $this->service->store($file, 'radiographies');

    expect($result['mime_type'])->toBe('image/png')
        ->and($result['filename'])->toMatch('/^[0-9a-f\-]{36}\.png$/')
        ->and($result['stored_path'])->toContain("tenants/{$this->tenant->id}/uploads/radiographies/");

    Storage::disk('local')->assertExists("tenants/{$this->tenant->id}/uploads/radiographies/{$result['filename']}");
});

test('secure upload service rejects executable disguised as image or unpermitted mime type', function () {
    $dangerousFile = UploadedFile::fake()->createWithContent('malicious.php', '<?php phpinfo(); ?>');

    expect(fn () => $this->service->store($dangerousFile, 'documents'))
        ->toThrow(InvalidArgumentException::class);
});

test('secure upload service rejects file exceeding max size limit', function () {
    $largeFile = UploadedFile::fake()->create('large_scan.pdf', 15000); // 15MB

    // With a 5MB limit (5 * 1024 * 1024 bytes)
    $maxLimit = 5 * 1024 * 1024;

    expect(fn () => $this->service->store($largeFile, 'documents', $maxLimit))
        ->toThrow(InvalidArgumentException::class);
});
