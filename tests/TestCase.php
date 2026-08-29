<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    private string $tenantDatabaseDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantDatabaseDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bsdental-tests'.DIRECTORY_SEPARATOR.getmypid().'-'.bin2hex(random_bytes(8));

        if (! is_dir($this->tenantDatabaseDirectory) && ! mkdir($this->tenantDatabaseDirectory, 0700, true) && ! is_dir($this->tenantDatabaseDirectory)) {
            throw new RuntimeException("Unable to create isolated tenant test directory: {$this->tenantDatabaseDirectory}");
        }

        $this->app['config']->set('database.testing_tenant_directory', $this->tenantDatabaseDirectory);

        if (! Schema::connection('landlord')->hasTable('tenants')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/landlord',
                '--database' => 'landlord',
                '--realpath' => false,
            ]);
        }
    }

    public function tenantDatabasePath(string $filename): string
    {
        if ($filename !== basename($filename)) {
            throw new RuntimeException("Tenant test database filename must not contain a path: {$filename}");
        }

        return $this->tenantDatabaseDirectory.DIRECTORY_SEPARATOR.$filename;
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $landlordDatabase = (string) $app['config']->get('database.connections.landlord.database');

        if ($app->environment('testing') && $landlordDatabase !== ':memory:') {
            throw new RuntimeException(
                "Unsafe test configuration: landlord database must be ':memory:', got '{$landlordDatabase}'."
            );
        }

        return $app;
    }
}
