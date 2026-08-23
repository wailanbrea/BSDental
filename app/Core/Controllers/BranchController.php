<?php

namespace App\Core\Controllers;

use App\Core\Models\Branch;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display a listing of branches.
     */
    public function index(): Response
    {
        $branchIds = Auth::guard('web')->user()?->branchScopeIds();
        $branches = Branch::with(['rooms'])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
            ->withCount(['rooms', 'professionals'])
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return Inertia::render('Clinic/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_main' => ['boolean'],
        ]);

        if (! empty($validated['is_main'])) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch = Branch::create($validated);

        $this->auditLogger->logTenant('branch.created', 'Branch', $branch->id, [
            'name' => $branch->name,
            'code' => $branch->code,
        ]);

        return redirect()->back()->with('success', 'Sucursal creada exitosamente.');
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($validated['is_main']) && ! $branch->is_main) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch->update($validated);

        $this->auditLogger->logTenant('branch.updated', 'Branch', $branch->id, [
            'name' => $branch->name,
        ]);

        return redirect()->back()->with('success', 'Sucursal actualizada exitosamente.');
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        $branch->delete();

        $this->auditLogger->logTenant('branch.deleted', 'Branch', $branch->id, [
            'name' => $branch->name,
        ]);

        return redirect()->back()->with('success', 'Sucursal eliminada exitosamente.');
    }
}
