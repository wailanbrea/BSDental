<?php

namespace App\Core\Controllers;

use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcedureCatalogController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display procedure catalog.
     */
    public function index(): Response
    {
        $categories = ProcedureCategory::with('procedures')->get();

        return Inertia::render('Clinic/Procedures/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a new procedure in the catalog.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:tenant.procedure_categories,id'],
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'estimated_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_lab' => ['boolean'],
        ]);

        $procedure = Procedure::create([
            'category_id' => $validated['category_id'],
            'code' => $validated['code'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'estimated_minutes' => $validated['estimated_minutes'],
            'tax_rate' => $validated['tax_rate'] ?? 0.00,
            'requires_lab' => $validated['requires_lab'] ?? false,
            'is_active' => true,
        ]);

        $this->auditLogger->logTenant('procedure.created', 'Procedure', $procedure->id, [
            'name' => $procedure->name,
            'price' => $procedure->price,
        ]);

        return redirect()->back()->with('success', 'Procedimiento registrado en el arancel.');
    }
}
