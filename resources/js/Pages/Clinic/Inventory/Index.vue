<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Layers, Plus, ShoppingCart, AlertTriangle, ArrowDownRight, ArrowUpRight } from 'lucide-vue-next'

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

const isCreateItemModal = ref(false)
const isPurchaseModal = ref(false)

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
</script>

<template>
    <ClinicLayout>
<div class="clinical-precision-page">
    <Head title="Inventario Clínico & Insumos — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <Layers class="w-6 h-6 text-teal-400" /> Inventario Clínico & Stock
                    </h1>
                    <p class="text-sm text-slate-400">Control de insumos dentales por lote, trazabilidad y almacenes</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-sm transition border border-slate-700"
                        @click="isCreateItemModal = true"
                    >
                        <Plus class="w-4 h-4" /> Nuevo Insumo
                    </button>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-lg text-sm transition"
                        @click="isPurchaseModal = true"
                    >
                        <ShoppingCart class="w-4 h-4" /> Registrar Compra / Lote
                    </button>
                </div>
            </div>

            <!-- Items and Stock Table -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Existencias Actuales</h2>

                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/80 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3">Insumo</th>
                            <th class="px-4 py-3">Categoría</th>
                            <th class="px-4 py-3 text-right">Costo Ref.</th>
                            <th class="px-4 py-3 text-center">Stock Mín.</th>
                            <th class="px-4 py-3 text-right">Stock Actual</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-xs">
                        <tr v-for="it in items" :key="it.id" class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 font-semibold text-white">
                                {{ it.name }}
                                <span v-if="it.sku" class="block font-mono text-[10px] text-slate-500">{{ it.sku }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">{{ it.category_name }}</td>
                            <td class="px-4 py-3 text-right font-mono">${{ it.cost_price.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-center font-mono">{{ it.min_stock }} {{ it.unit }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-white">{{ it.total_stock }} {{ it.unit }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="[
                                        it.is_low_stock ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
                                    ]"
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-full border inline-flex items-center gap-1"
                                >
                                    <AlertTriangle v-if="it.is_low_stock" class="w-3 h-3" />
                                    {{ it.is_low_stock ? 'Bajo Stock' : 'Disponible' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Stock Movements Ledger -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Libro Mayor de Movimientos (Kardex)</h2>

                <div class="space-y-2">
                    <div v-for="mov in recentMovements" :key="mov.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex items-center justify-between text-xs">
                        <div class="flex items-center gap-3">
                            <span :class="[mov.quantity > 0 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400']" class="p-2 rounded-xl">
                                <ArrowUpRight v-if="mov.quantity > 0" class="w-4 h-4" />
                                <ArrowDownRight v-else class="w-4 h-4" />
                            </span>
                            <div>
                                <div class="font-bold text-white">{{ mov.item.name }}</div>
                                <div class="text-slate-400 text-[11px]">{{ mov.notes || mov.type }} en {{ mov.warehouse.name }}</div>
                            </div>
                        </div>

                        <div class="text-right">
                            <span :class="[mov.quantity > 0 ? 'text-emerald-400' : 'text-amber-400']" class="font-mono font-bold text-sm block">
                                {{ mov.quantity > 0 ? '+' : '' }}{{ mov.quantity }} {{ mov.item.unit }}
                            </span>
                            <span class="text-slate-500 text-[10px]">{{ mov.created_at }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for New Item -->
            <div v-if="isCreateItemModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Nuevo Insumo de Inventario</h2>
                    <button class="text-slate-400 hover:text-white" @click="isCreateItemModal = false">×</button>
                </div>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitNewItem">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Categoría</label>
                        <select v-model="itemForm.category_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">SKU / Código</label>
                        <input v-model="itemForm.sku" type="text" placeholder="Ej. MAT-RES-01" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nombre del Insumo *</label>
                        <input v-model="itemForm.name" type="text" required placeholder="Ej. Resina Filtek Z350 XT A2" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Unidad de Presentación</label>
                        <select v-model="itemForm.unit" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
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
                        <label class="block text-xs font-medium text-slate-400 mb-1">Stock Mínimo</label>
                        <input v-model.number="itemForm.min_stock" type="number" min="0" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isCreateItemModal = false">Cancelar</button>
                        <button type="submit" :disabled="itemForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Guardar Insumo</button>
                    </div>
                </form>
            </div>

            <!-- Modal for Purchase Entry -->
            <div v-if="isPurchaseModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Ingreso por Compra (Lote)</h2>
                    <button class="text-slate-400 hover:text-white" @click="isPurchaseModal = false">×</button>
                </div>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitPurchase">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Insumo</label>
                        <select v-model="purchaseForm.inventory_item_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Almacén de Destino</label>
                        <select v-model="purchaseForm.warehouse_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Número de Lote *</label>
                        <input v-model="purchaseForm.batch_number" type="text" required placeholder="Ej. LOT-2026-08" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Fecha de Vencimiento (opcional)</label>
                        <input v-model="purchaseForm.expires_at" type="date" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Cantidad Comprada *</label>
                        <input v-model.number="purchaseForm.quantity" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Costo Unitario ($) *</label>
                        <input v-model.number="purchaseForm.cost_per_unit" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isPurchaseModal = false">Cancelar</button>
                        <button type="submit" :disabled="purchaseForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Registrar Ingreso</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
