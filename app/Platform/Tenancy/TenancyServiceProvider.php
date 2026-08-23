<?php

namespace App\Platform\Tenancy;

use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register tenancy services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, function () {
            return new TenantContext;
        });

        $this->app->alias(TenantContext::class, 'tenant.context');
    }

    /**
     * Bootstrap tenancy services.
     */
    public function boot(): void
    {
        //
    }
}
