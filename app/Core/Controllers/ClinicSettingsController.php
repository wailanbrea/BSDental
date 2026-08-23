<?php

namespace App\Core\Controllers;

use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClinicSettingsController extends Controller
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Show clinic settings form.
     */
    public function edit(): Response
    {
        $tenant = $this->tenantContext->requireCurrent();

        return Inertia::render('Clinic/Settings/Edit', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'settings' => $tenant->settings ?? [
                    'currency' => 'USD',
                    'timezone' => 'America/Caracas',
                    'phone' => '',
                    'address' => '',
                ],
            ],
        ]);
    }

    /**
     * Update clinic settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $tenant = $this->tenantContext->requireCurrent();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'timezone' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = array_merge($tenant->settings ?? [], [
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
            'phone' => $validated['phone'] ?? '',
            'address' => $validated['address'] ?? '',
        ]);

        $tenant->name = $validated['name'];
        $tenant->settings = $settings;
        $tenant->save();

        $this->auditLogger->logTenant('clinic_settings.updated', 'Tenant', $tenant->id, [
            'name' => $tenant->name,
            'currency' => $validated['currency'],
            'timezone' => $validated['timezone'],
        ]);

        return redirect()->back()->with('success', 'Configuración de la clínica actualizada exitosamente.');
    }
}
