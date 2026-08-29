<?php

use App\Core\Auth\Database\Seeders\TenantRbacSeeder;
use App\Core\Auth\Models\User;
use App\Platform\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// Custom expectations

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you have the ability to create higher-level
| tests using functions that can be used across your test suite.
|
*/

// Custom functions

function grantTenantOwnerAccess(User $user): void
{
    (new TenantRbacSeeder)->run();
    $user->assignRole('Owner');
}

/**
 * Grant only the commercial modules exercised by an operational test.
 *
 * @param  list<string>  $modules
 */
function grantTenantModules(Tenant $tenant, array $modules): void
{
    $settings = $tenant->settings ?? [];
    $overrides = is_array($settings['module_overrides'] ?? null)
        ? $settings['module_overrides']
        : [];

    foreach ($modules as $module) {
        $overrides[$module] = true;
    }

    $settings['module_overrides'] = $overrides;
    $tenant->update(['settings' => $settings]);
}
