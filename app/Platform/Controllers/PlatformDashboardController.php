<?php

namespace App\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    /**
     * Display central platform admin dashboard.
     */
    public function index(): Response
    {
        /** @var PlatformUser $user */
        $user = Auth::guard('platform')->user();

        $tenants = Tenant::query()
            ->with(['domains', 'primaryDomain'])
            ->latest()
            ->take(20)
            ->get();

        $metrics = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
            'provisioning_tenants' => Tenant::where('status', 'provisioning')->count(),
        ];

        return Inertia::render('Platform/Dashboard', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'metrics' => $metrics,
            'tenants' => $tenants,
        ]);
    }
}
