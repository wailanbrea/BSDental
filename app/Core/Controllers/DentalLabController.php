<?php

namespace App\Core\Controllers;

use App\Core\Models\DentalLaboratory;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use App\Core\Services\DentalLabService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DentalLabController extends Controller
{
    public function __construct(
        protected DentalLabService $labService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display lab orders dashboard.
     */
    public function index(): Response
    {
        $laboratories = DentalLaboratory::withCount('orders')->get();
        $orders = LabOrder::with(['patient', 'laboratory', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Clinic/Lab/Index', [
            'laboratories' => $laboratories,
            'orders' => $orders,
        ]);
    }

    /**
     * Create new lab order.
     */
    public function storeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid', 'exists:tenant.patients,id'],
            'laboratory_id' => ['required', 'uuid', 'exists:tenant.dental_laboratories,id'],
            'treatment_plan_item_id' => ['nullable', 'uuid', 'exists:tenant.treatment_plan_items,id'],
            'tooth_number' => ['nullable', 'integer', 'min:11', 'max:85'],
            'work_description' => ['required', 'string'],
            'shade_guide' => ['nullable', 'string', 'max:50'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $patient = Patient::findOrFail($validated['patient_id']);
        $lab = DentalLaboratory::findOrFail($validated['laboratory_id']);
        $userId = Auth::guard('web')->id();

        $order = $this->labService->createOrder(
            $patient,
            $lab,
            $validated['work_description'],
            $validated['tooth_number'] ?? null,
            $validated['shade_guide'] ?? null,
            $validated['estimated_cost'] ?? 0.00,
            $validated['due_date'] ?? null,
            $validated['treatment_plan_item_id'] ?? null,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('lab_order.created', 'LabOrder', $order->id, [
            'order_number' => $order->order_number,
            'laboratory_id' => $lab->id,
            'patient_id' => $patient->id,
        ]);

        return redirect()->back()->with('success', "Orden de laboratorio {$order->order_number} creada con éxito.");
    }

    /**
     * Update order state and final cost.
     */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $order = LabOrder::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,ordered,sent,in_progress,ready,received,delivered,cancelled'],
            'final_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $updated = $this->labService->updateStatus(
            $order,
            $validated['status'],
            $validated['final_cost'] ?? null
        );

        $this->auditLogger->logTenant('lab_order.status_updated', 'LabOrder', $updated->id, [
            'order_number' => $updated->order_number,
            'status' => $updated->status,
            'final_cost' => $updated->final_cost,
        ]);

        return redirect()->back()->with('success', "Orden {$updated->order_number} actualizada a {$updated->status}.");
    }
}
