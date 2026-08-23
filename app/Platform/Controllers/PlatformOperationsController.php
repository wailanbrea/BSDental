<?php

namespace App\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Operations\PlatformOperationsService;
use App\Platform\Plans\Models\Plan;
use App\Platform\Security\AuditLogger;
use App\Platform\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlatformOperationsController extends Controller
{
    public function __construct(
        protected PlatformOperationsService $opsService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display Platform Operations Dashboard.
     */
    public function dashboard(): Response
    {
        $metrics = $this->opsService->getGlobalMetrics();

        $tenants = Tenant::with(['domains', 'plan'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Tenant $t): array {
                $health = $this->opsService->checkTenantHealth($t);
                $primaryDomain = $t->domains->where('is_primary', true)->first();

                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'status' => $t->status,
                    'plan_name' => $t->plan instanceof Plan ? $t->plan->name : 'Sin Plan',
                    'primary_domain' => $primaryDomain !== null ? $primaryDomain->domain : '',
                    'db_connected' => $health['db_connected'],
                    'tables_count' => $health['tables_count'],
                    'backup_status' => $health['backup_status'],
                    'created_at' => $t->created_at?->toDateTimeString() ?? '',
                ];
            });

        $plans = Plan::where('is_active', true)->get();

        return Inertia::render('Platform/Operations/Dashboard', [
            'metrics' => $metrics,
            'tenants' => $tenants,
            'plans' => $plans,
        ]);
    }

    /**
     * Trigger isolated private backup for a tenant.
     */
    public function triggerBackup(string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);
        $adminId = (string) Auth::guard('platform')->id();

        $backupPath = $this->opsService->triggerTenantBackup($tenant, $adminId);

        return redirect()->back()->with('success', "Respaldo generado con éxito en: {$backupPath}");
    }

    /**
     * Update tenant plan and subscription entitlement.
     */
    public function updatePlan(Request $request, string $tenantId): RedirectResponse
    {
        $tenant = Tenant::findOrFail($tenantId);

        $validated = $request->validate([
            'plan_id' => ['required', 'uuid', 'exists:landlord.plans,id'],
            'module_overrides' => ['nullable', 'array'],
        ]);

        $tenant->update([
            'plan_id' => $validated['plan_id'],
            'module_overrides' => $validated['module_overrides'] ?? null,
        ]);

        $this->auditLogger->logPlatform('tenant.plan_updated', 'Tenant', $tenant->id, [
            'tenant_name' => $tenant->name,
            'plan_id' => $validated['plan_id'],
        ], $tenant);

        return redirect()->back()->with('success', "Plan de suscripción de {$tenant->name} actualizado.");
    }
}
