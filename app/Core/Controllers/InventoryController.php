<?php

namespace App\Core\Controllers;

use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\StockMovement;
use App\Core\Models\Warehouse;
use App\Core\Services\InventoryStockService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryStockService $stockService,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display inventory dashboard with stock levels and movements.
     */
    public function index(): Response
    {
        $branchIds = Auth::guard('web')->user()?->branchScopeIds();
        $categories = InventoryCategory::all();
        $warehouses = Warehouse::with('branch')->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))->get();
        $items = InventoryItem::with(['category', 'batches' => fn ($query) => $query->when($branchIds !== null, fn ($batchQuery) => $batchQuery->whereIn('warehouse_id', $warehouses->pluck('id'))), 'batches.warehouse'])->get()->map(function ($item) {
            $visibleStock = (float) $item->batches->sum('current_quantity');

            return [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'category_name' => $item->category->name,
                'sku' => $item->sku,
                'name' => $item->name,
                'unit' => $item->unit,
                'min_stock' => $item->min_stock,
                'cost_price' => $item->cost_price,
                'total_stock' => $visibleStock,
                'is_low_stock' => $visibleStock <= $item->min_stock,
                'batches' => $item->batches,
            ];
        });

        $recentMovements = StockMovement::with(['item', 'warehouse', 'createdBy'])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('warehouse_id', $warehouses->pluck('id')))
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Clinic/Inventory/Index', [
            'categories' => $categories,
            'warehouses' => $warehouses,
            'items' => $items,
            'recentMovements' => $recentMovements,
        ]);
    }

    /**
     * Store new inventory item.
     */
    public function storeItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'uuid', 'exists:tenant.inventory_categories,id'],
            'sku' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'in:unit,box,syringe,bottle,pair,gram,ml'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
        ]);

        $item = InventoryItem::create([
            'category_id' => $validated['category_id'],
            'sku' => $validated['sku'] ?? null,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'min_stock' => $validated['min_stock'],
            'cost_price' => $validated['cost_price'],
            'is_active' => true,
        ]);

        $this->auditLogger->logTenant('inventory.item_created', 'InventoryItem', $item->id, [
            'name' => $item->name,
            'sku' => $item->sku,
        ]);

        return redirect()->back()->with('success', "Insumo {$item->name} registrado.");
    }

    /**
     * Record purchase batch and stock movement.
     */
    public function recordPurchase(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'uuid', 'exists:tenant.inventory_items,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:tenant.warehouses,id'],
            'batch_number' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'cost_per_unit' => ['required', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $userId = Auth::guard('web')->id();

        $movement = $this->stockService->recordPurchase(
            $item,
            $warehouse,
            $validated['batch_number'],
            $validated['quantity'],
            $validated['cost_per_unit'],
            $validated['expires_at'] ?? null,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('inventory.purchase_recorded', 'StockMovement', $movement->id, [
            'item_id' => $item->id,
            'quantity' => $movement->quantity,
            'batch_number' => $validated['batch_number'],
        ]);

        return redirect()->back()->with('success', "Compra de {$item->name} ({$movement->quantity} {$item->unit}) ingresada con éxito.");
    }

    /**
     * Record manual stock adjustment / loss (INV-02).
     */
    public function recordAdjustment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'uuid', 'exists:tenant.inventory_items,id'],
            'warehouse_id' => ['required', 'uuid', 'exists:tenant.warehouses,id'],
            'type' => ['required', 'string', 'in:adjustment_in,adjustment_out,waste_loss'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
            'batch_id' => ['nullable', 'uuid', 'exists:tenant.inventory_batches,id'],
        ]);

        $item = InventoryItem::findOrFail($validated['inventory_item_id']);
        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $batch = ! empty($validated['batch_id']) ? InventoryBatch::find($validated['batch_id']) : null;
        $userId = Auth::guard('web')->id();

        $movement = $this->stockService->recordManualAdjustment(
            $item,
            $warehouse,
            $validated['type'],
            (float) $validated['quantity'],
            $validated['reason'],
            $batch,
            $userId ? (string) $userId : null
        );

        $this->auditLogger->logTenant('inventory.adjustment_recorded', 'StockMovement', $movement->id, [
            'item_id' => $item->id,
            'type' => $movement->type,
            'quantity' => $movement->quantity,
            'reason' => $validated['reason'],
        ]);

        return redirect()->back()->with('success', "Ajuste de inventario registrado ({$movement->type}).");
    }

    /**
     * View full item Kardex history (INV-01).
     */
    public function kardex(string $itemId): Response
    {
        $item = InventoryItem::with(['category', 'batches.warehouse'])->findOrFail($itemId);
        $kardexData = $this->stockService->getItemKardex($item);

        return Inertia::render('Clinic/Inventory/Kardex', [
            'kardex' => $kardexData,
        ]);
    }
}
