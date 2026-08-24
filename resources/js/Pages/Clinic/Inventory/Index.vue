<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { AlertTriangle, ArrowDownRight, ArrowUpRight, Building2, DollarSign, FileText, Layers, Package, Plus, Search, ShoppingCart, SlidersHorizontal, X } from 'lucide-vue-next'

interface CategoryItem {
    id: string
    name: string
}

interface WarehouseItem {
    id: string
    name: string
    branch?: { name: string }
}

interface BatchItem {
    id: string
    batch_number: string
    current_quantity: number
    expires_at: string | null
}

interface ItemSummary {
    id: string
    category_id: string
    category_name: string
    sku: string | null
    name: string
    unit: string
    min_stock: number
    cost_price: number
    total_stock: number
    is_low_stock: boolean
    batches: BatchItem[]
}

interface MovementSummary {
    id: string
    type: string
    quantity: number
    previous_stock: number
    new_stock: number
    unit_cost: number
    total_cost: number
    notes: string | null
    created_at: string
    item: { name: string; unit: string }
    warehouse: { name: string }
}

const props = defineProps<{
    categories: CategoryItem[]
    warehouses: WarehouseItem[]
    items: ItemSummary[]
    recentMovements: MovementSummary[]
}>()

const searchQuery = ref('')
const selectedCategory = ref('')
const onlyLowStock = ref(false)

const isCreateItemModal = ref(false)
const isPurchaseModal = ref(false)
const isAdjustmentModal = ref(false)

const itemForm = useForm({
    category_id: props.categories[0]?.id || '',
    sku: '',
    name: '',
    unit: 'unit',
    min_stock: 5,
    cost_price: 0,
})

const purchaseForm = useForm({
    inventory_item_id: props.items[0]?.id || '',
    warehouse_id: props.warehouses[0]?.id || '',
    batch_number: '',
    quantity: 10,
    cost_per_unit: 0,
    expires_at: '',
})

const adjustmentForm = useForm({
    inventory_item_id: props.items[0]?.id || '',
    warehouse_id: props.warehouses[0]?.id || '',
    type: 'adjustment_in',
    quantity: 1,
    reason: '',
})

const filteredItems = computed(() => {
    return props.items.filter((item) => {
        const matchesSearch = searchQuery.value === '' || 
            item.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (item.sku && item.sku.toLowerCase().includes(searchQuery.value.toLowerCase()))
        
        const matchesCategory = selectedCategory.value === '' || item.category_id === selectedCategory.value
        const matchesLowStock = !onlyLowStock.value || item.is_low_stock

        return matchesSearch && matchesCategory && matchesLowStock
    })
})

const totalInventoryValue = computed(() => {
    return props.items.reduce((acc, it) => acc + (it.total_stock * it.cost_price), 0)
})

const lowStockCount = computed(() => {
    return props.items.filter(it => it.is_low_stock).length
})

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function movementLabel(type: string) {
    return ({
        purchase_in: 'Compra / Ingreso Lote',
        procedure_consumption: 'Consumo Clínico',
        adjustment_in: 'Ajuste Ingreso (+)',
        adjustment_out: 'Ajuste Egreso (-)',
        waste_loss: 'Merma / Rotura',
        transfer_in: 'Transferencia Entrada',
        transfer_out: 'Transferencia Salida',
    } as Record<string, string>)[type] || type
}

function submitNewItem() {
    itemForm.post('/inventory/items', {
        onSuccess: () => {
            isCreateItemModal.value = false
            itemForm.reset()
        },
    })
}

function submitPurchase() {
    purchaseForm.post('/inventory/purchases', {
        onSuccess: () => {
            isPurchaseModal.value = false
            purchaseForm.reset()
        },
    })
}

function submitAdjustment() {
    adjustmentForm.post('/inventory/adjustments', {
        onSuccess: () => {
            isAdjustmentModal.value = false
            adjustmentForm.reset()
        },
    })
}
</script>

<template>
    <ClinicLayout>
        <Head title="Inventario Clínico & Insumos — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <Layers class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Inventario Clínico & Stock
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Control de insumos dentales por lote, trazabilidad Kardex y gestión multialmacén
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg border border-[#BDC9C6] transition shadow-xs"
                        @click="isAdjustmentModal = true"
                    >
                        <SlidersHorizontal class="w-3.5 h-3.5 text-[#505F76]" /> Ajuste / Merma
                    </button>
                    <button
                        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg border border-[#BDC9C6] transition shadow-xs"
                        @click="isCreateItemModal = true"
                    >
                        <Plus class="w-3.5 h-3.5 text-[#005C55]" /> Nuevo Insumo
                    </button>
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isPurchaseModal = true"
                    >
                        <ShoppingCart class="w-3.5 h-3.5" /> Registrar Compra / Lote
                    </button>
                </div>
            </div>

            <!-- Bento Summary Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-[#505F76]">Total Insumos Registrados</span>
                        <Package class="w-4 h-4 text-[#005C55]" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">{{ items.length }}</span>
                        <span class="text-xs text-[#505F76] ml-1">artículos</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-[#505F76]">Valor Estimado del Stock</span>
                        <DollarSign class="w-4 h-4 text-[#005C55]" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-[#005C55]">{{ formatMoney(totalInventoryValue) }}</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-[#505F76]">Insumos en Alerta (Bajo Stock)</span>
                        <AlertTriangle class="w-4 h-4" :class="lowStockCount > 0 ? 'text-[#BA1A1A]' : 'text-slate-400'" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular" :class="lowStockCount > 0 ? 'text-[#BA1A1A]' : 'text-[#131B2E]'">
                            {{ lowStockCount }}
                        </span>
                        <span class="text-xs text-[#505F76] ml-1">en nivel crítico</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-[#505F76]">Almacenes / Bodegas</span>
                        <Building2 class="w-4 h-4 text-[#005C55]" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">{{ warehouses.length }}</span>
                        <span class="text-xs text-[#505F76] ml-1">habilitados</span>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="relative w-full sm:w-80">
                    <Search class="w-4 h-4 text-[#505F76] absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Buscar por nombre o SKU..."
                        class="w-full pl-9 pr-3 py-1.5 text-xs bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] focus:outline-none focus:border-[#005C55] focus:bg-white transition placeholder:text-[#505F76]"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <select
                        v-model="selectedCategory"
                        class="px-3 py-1.5 text-xs bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] focus:outline-none focus:border-[#005C55] focus:bg-white"
                    >
                        <option value="">Todas las Categorías</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>

                    <button
                        :class="[
                            'px-3 py-1.5 text-xs font-medium rounded-lg border transition flex items-center gap-1.5',
                            onlyLowStock 
                                ? 'bg-rose-50 text-[#BA1A1A] border-rose-300 font-semibold' 
                                : 'bg-[#F8FAFC] text-[#505F76] border-[#BDC9C6] hover:bg-white'
                        ]"
                        @click="onlyLowStock = !onlyLowStock"
                    >
                        <AlertTriangle class="w-3.5 h-3.5" /> Solo Bajo Stock
                    </button>
                </div>
            </div>

            <!-- Items & Stock Table -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
                    <div>
                        <h2 class="font-section-title text-[#131B2E]">Existencias de Insumos</h2>
                        <p class="text-xs text-[#505F76]">Listado de insumos con control de lotes y acceso al Kardex detallado</p>
                    </div>
                    <span class="text-xs font-mono text-[#505F76]">{{ filteredItems.length }} de {{ items.length }} insumos</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Insumo</th>
                                <th class="px-4 py-3 font-semibold">Categoría</th>
                                <th class="px-4 py-3 font-semibold text-right">Costo Ref.</th>
                                <th class="px-4 py-3 font-semibold text-center">Stock Mín.</th>
                                <th class="px-4 py-3 font-semibold text-right">Stock Actual</th>
                                <th class="px-4 py-3 font-semibold text-center">Estado</th>
                                <th class="px-5 py-3 font-semibold text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="it in filteredItems" :key="it.id" class="hover:bg-[#F8FAFC] transition-colors h-12">
                                <td class="px-5 py-2.5 font-medium text-[#131B2E]">
                                    <div class="flex items-center gap-2">
                                        <div>
                                            <span class="font-semibold block text-[#131B2E]">{{ it.name }}</span>
                                            <span v-if="it.sku" class="font-mono text-[10px] text-[#505F76]">SKU: {{ it.sku }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-[#505F76]">{{ it.category_name }}</td>
                                <td class="px-4 py-2.5 text-right font-data-tabular text-[#131B2E]">{{ formatMoney(it.cost_price) }}</td>
                                <td class="px-4 py-2.5 text-center font-data-tabular text-[#505F76]">{{ it.min_stock }} {{ it.unit }}</td>
                                <td class="px-4 py-2.5 text-right font-data-tabular font-bold" :class="it.is_low_stock ? 'text-[#BA1A1A]' : 'text-[#005C55]'">
                                    {{ it.total_stock }} {{ it.unit }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span
                                        :class="[
                                            'px-2.5 py-0.5 rounded-full text-[11px] font-semibold border inline-flex items-center gap-1',
                                            it.is_low_stock 
                                                ? 'bg-rose-50 text-[#BA1A1A] border-rose-200' 
                                                : 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                        ]"
                                    >
                                        <AlertTriangle v-if="it.is_low_stock" class="w-3 h-3" />
                                        {{ it.is_low_stock ? 'Bajo Stock' : 'Disponible' }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-right">
                                    <Link
                                        :href="`/inventory/items/${it.id}/kardex`"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-[#005C55] hover:text-[#004742] bg-[#005C55]/5 hover:bg-[#005C55]/10 rounded-md transition"
                                    >
                                        <FileText class="w-3 h-3" /> Ver Kardex
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="filteredItems.length === 0">
                                <td colspan="7" class="px-5 py-8 text-center text-xs text-[#505F76]">
                                    No se encontraron insumos que coincidan con los filtros aplicados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Movements Ledger -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-section-title text-[#131B2E]">Últimos Movimientos de Inventario</h2>
                        <p class="text-xs text-[#505F76]">Registro de ingresos, consumos clínicos y ajustes recientes</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div 
                        v-for="mov in recentMovements" 
                        :key="mov.id" 
                        class="p-3.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl flex items-center justify-between text-xs hover:border-[#BDC9C6] transition"
                    >
                        <div class="flex items-center gap-3">
                            <span 
                                :class="[
                                    'p-2 rounded-lg',
                                    mov.quantity > 0 ? 'bg-emerald-100/70 text-emerald-800' : 'bg-rose-100/70 text-rose-800'
                                ]"
                            >
                                <ArrowUpRight v-if="mov.quantity > 0" class="w-4 h-4" />
                                <ArrowDownRight v-else class="w-4 h-4" />
                            </span>
                            <div>
                                <div class="font-bold text-[#131B2E]">{{ mov.item?.name || 'Insumo' }}</div>
                                <div class="text-[#505F76] text-[11px]">
                                    {{ movementLabel(mov.type) }} • {{ mov.notes || 'Sin nota' }} en <span class="font-medium text-[#131B2E]">{{ mov.warehouse?.name || 'Bodega Principal' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-right">
                            <span 
                                :class="[
                                    'font-data-tabular font-bold text-xs block',
                                    mov.quantity > 0 ? 'text-emerald-700' : 'text-[#BA1A1A]'
                                ]"
                            >
                                {{ mov.quantity > 0 ? '+' : '' }}{{ mov.quantity }} {{ mov.item?.unit || 'unid' }}
                            </span>
                            <span class="text-[#505F76] text-[10px] font-mono">{{ mov.created_at }}</span>
                        </div>
                    </div>

                    <div v-if="recentMovements.length === 0" class="p-6 text-center text-xs text-[#505F76]">
                        No hay movimientos registrados recientemente.
                    </div>
                </div>
            </div>

            <!-- Modal: Nuevo Insumo -->
            <div v-if="isCreateItemModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isCreateItemModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Plus class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Crear Nuevo Insumo</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isCreateItemModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitNewItem">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Categoría *</label>
                            <select v-model="itemForm.category_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">SKU / Código</label>
                            <input v-model="itemForm.sku" type="text" placeholder="Ej. MAT-RES-01" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombre del Insumo *</label>
                            <input v-model="itemForm.name" type="text" required placeholder="Ej. Resina Filtek Z350 XT A2" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Unidad de Presentación</label>
                            <select v-model="itemForm.unit" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option value="unit">Unidad</option>
                                <option value="box">Caja</option>
                                <option value="syringe">Jeringa</option>
                                <option value="bottle">Frasco</option>
                                <option value="pair">Par</option>
                                <option value="gram">Gramo</option>
                                <option value="ml">Mililitro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Stock Mínimo de Alerta</label>
                            <input v-model.number="itemForm.min_stock" type="number" min="0" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isCreateItemModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Guardar Insumo
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Registrar Compra / Lote -->
            <div v-if="isPurchaseModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isPurchaseModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <ShoppingCart class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Registrar Ingreso por Compra (Lote)</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isPurchaseModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitPurchase">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Insumo *</label>
                            <select v-model="purchaseForm.inventory_item_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Almacén / Bodega de Destino *</label>
                            <select v-model="purchaseForm.warehouse_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Número de Lote *</label>
                            <input v-model="purchaseForm.batch_number" type="text" required placeholder="Ej. LOT-2026-08" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Fecha de Vencimiento</label>
                            <input v-model="purchaseForm.expires_at" type="date" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Cantidad *</label>
                            <input v-model.number="purchaseForm.quantity" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Costo Unitario ($) *</label>
                            <input v-model.number="purchaseForm.cost_per_unit" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isPurchaseModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="purchaseForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Registrar Ingreso
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal: Ajuste Manual / Merma -->
            <div v-if="isAdjustmentModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isAdjustmentModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <SlidersHorizontal class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Ajuste Manual de Inventario / Merma</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isAdjustmentModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitAdjustment">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Insumo a Ajustar *</label>
                            <select v-model="adjustmentForm.inventory_item_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Tipo de Ajuste *</label>
                            <select v-model="adjustmentForm.type" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option value="adjustment_in">Ingreso Manual (+)</option>
                                <option value="adjustment_out">Egreso Manual (-)</option>
                                <option value="waste_loss">Merma / Rotura / Caducado (-)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Almacén *</label>
                            <select v-model="adjustmentForm.warehouse_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Cantidad a Ajustar *</label>
                            <input v-model.number="adjustmentForm.quantity" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Motivo / Justificación *</label>
                            <textarea v-model="adjustmentForm.reason" required rows="2" placeholder="Explica la causa del ajuste (ej. Frasco quebrado en gabinete 2, corrección de conteo físico)..." class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]"></textarea>
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isAdjustmentModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="adjustmentForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Guardar Ajuste
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
