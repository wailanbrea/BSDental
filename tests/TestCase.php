<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
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
