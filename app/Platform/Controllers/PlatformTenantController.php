<?php

namespace App\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Actions\ResumeTenantAction;
use App\Platform\Actions\SuspendTenantAction;
use App\Platform\Plans\Models\Plan;
use App\Platform\Provisioning\TenantProvisioningPipeline;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\TenantContext;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformTenantController extends Controller
{
    /**
     * Display a listing of tenants.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();

        $query = Tenant::query()
            ->with(['domains', 'primaryDomain', 'plan'])
            ->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $tenants = $query->paginate(15)->withQueryString();
        $plans = Plan::where('is_active', true)->get();

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => $tenants,
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new tenant.
     */
    public function create(): Response
    {
        return Inertia::render('Platform/Tenants/Create', [
            'plans' => Plan::where('is_active', true)->get(),
        ]);
    }

    /**
     * Store a newly created tenant using the provisioning pipeline.
     */
    public function store(Request $request, TenantProvisioningPipeline $pipeline): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:landlord.tenants,slug'],
            'domain' => ['required', 'string', 'max:255', 'unique:landlord.tenant_domains,domain'],
            'plan_id' => ['required', 'string', 'exists:landlord.plans,id'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_password' => ['required', 'string', 'min:8'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = $pipeline->run($validated);

        return redirect()->route('platform.tenants.show', $tenant->id)
            ->with('status', 'Organización aprovisionada exitosamente.');
    }

    /**
     * Display the specified tenant details and operational health.
     */
    public function show(string $id, TenantContext $context): Response
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::query()
            ->with(['domains', 'primaryDomain', 'plan'])
            ->findOrFail($id);

        $dbHealth = 'unreachable';
        $migrationCount = 0;
        $error = null;

        try {
            $context->execute($tenant, function () use (&$dbHealth, &$migrationCount) {
                DB::connection('tenant')->getPdo();
                $dbHealth = 'healthy';
                $migrationCount = DB::connection('tenant')->table('migrations')->count();
            });
        } catch (Exception $e) {
            $dbHealth = 'error';
            $error = $e->getMessage();
        }

        return Inertia::render('Platform/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'database_name' => $tenant->database_name,
                'plan' => $tenant->plan,
                'domains' => $tenant->domains,
                'settings' => $tenant->settings,
                'created_at' => $tenant->created_at?->toISOString(),
            ],
            'health' => [
                'database_status' => $dbHealth,
                'applied_migrations' => $migrationCount,
                'last_checked_at' => now()->toISOString(),
                'error' => $error,
            ],
        ]);
    }

    /**
     * Suspend an active tenant.
     */
    public function suspend(string $id, Request $request, SuspendTenantAction $action): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);
        $reason = $request->string('reason', 'Suspendido por administración de plataforma')->toString();

        $action->execute($tenant, $reason);

        return back()->with('status', "Organización {$tenant->name} suspendida.");
    }

    /**
     * Resume a suspended tenant.
     */
    public function resume(string $id, ResumeTenantAction $action): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail($id);

        $action->execute($tenant);

        return back()->with('status', "Organización {$tenant->name} reactivada.");
    }
}
