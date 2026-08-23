<?php

namespace App\Providers;

use App\Platform\Auth\Models\PlatformUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?object $user = null): bool {
            /** @var PlatformUser|null $platformUser */
            $platformUser = Auth::guard('platform')->user() ?? ($user instanceof PlatformUser ? $user : null);

            return $platformUser !== null && in_array($platformUser->role, ['superadmin', 'admin'], true);
        });
    }
}
