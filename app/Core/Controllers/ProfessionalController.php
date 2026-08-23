<?php

namespace App\Core\Controllers;

use App\Core\Models\Branch;
use App\Core\Models\Professional;
use App\Core\Models\Specialty;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfessionalController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a listing of professionals.
     */
    public function index(): Response
    {
        $professionals = Professional::with(['specialties', 'branches', 'user'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $specialties = Specialty::where('is_active', true)->orderBy('name')->get();
        $branches = Branch::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Clinic/Professionals/Index', [
            'professionals' => $professionals,
            'specialties' => $specialties,
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created professional.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'specialty_ids' => ['array'],
            'specialty_ids.*' => ['uuid', 'exists:tenant.specialties,id'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['uuid', 'exists:tenant.branches,id'],
        ]);

        $professional = Professional::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'license_number' => $validated['license_number'] ?? null,
            'color' => $validated['color'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => true,
        ]);

        if (! empty($validated['specialty_ids'])) {
            $professional->specialties()->sync($validated['specialty_ids']);
        }

        if (! empty($validated['branch_ids'])) {
            $professional->branches()->sync($validated['branch_ids']);
        }

        $this->auditLogger->logTenant('professional.created', 'Professional', $professional->id, [
            'name' => $professional->full_name,
            'license' => $professional->license_number,
        ]);

        return redirect()->back()->with('success', 'Profesional registrado exitosamente.');
    }

    /**
     * Update the specified professional.
     */
    public function update(Request $request, Professional $professional): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:50'],
            'color' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['boolean'],
            'specialty_ids' => ['array'],
            'specialty_ids.*' => ['uuid', 'exists:tenant.specialties,id'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['uuid', 'exists:tenant.branches,id'],
        ]);

        $professional->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'license_number' => $validated['license_number'] ?? null,
            'color' => $validated['color'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? $professional->is_active,
        ]);

        if (isset($validated['specialty_ids'])) {
            $professional->specialties()->sync($validated['specialty_ids']);
        }

        if (isset($validated['branch_ids'])) {
            $professional->branches()->sync($validated['branch_ids']);
        }

        $this->auditLogger->logTenant('professional.updated', 'Professional', $professional->id, [
            'name' => $professional->full_name,
        ]);

        return redirect()->back()->with('success', 'Profesional actualizado exitosamente.');
    }

    /**
     * Remove the specified professional.
     */
    public function destroy(Professional $professional): RedirectResponse
    {
        $professional->delete();

        $this->auditLogger->logTenant('professional.deleted', 'Professional', $professional->id, [
            'name' => $professional->full_name,
        ]);

        return redirect()->back()->with('success', 'Profesional eliminado exitosamente.');
    }
}
