<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, Printer } from 'lucide-vue-next'

interface StockMovementDetail {
    id: string
    type: string
    quantity: number
    previous_stock: number
    new_stock: number
    unit_cost: number
    total_cost: number
    notes?: string
    created_at: string
    warehouse?: { name: string }
    batch?: { batch_number: string; expires_at?: string }
    created_by?: { name: string }
}

interface KardexData {
    item: {
        id: string
        sku?: string
        name: string
        unit: string
        min_stock: number
        cost_price: number
        category?: { name: string }
        batches: Array<{
            id: string
            batch_number: string
            current_quantity: number
            cost_per_unit: number
            expires_at?: string
            warehouse?: { name: string }
        }>
    }
    total_stock: number
    movements: StockMovementDetail[]
}

defineProps<{
    kardex: KardexData
}>()

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function triggerPrint() {
    window.print()
}

function movementLabel(type: string) {
    return ({
        purchase_in: 'Compra / Ingreso Lote',
        procedure_consumption: 'Consumo Clínico',
        adjustment_in: 'Ajuste Ingreso (+)',
        adjustment_out: 'Ajuste Egreso (-)',
        waste_loss: 'Merma / Caducidad / Rotura',
        transfer_in: 'Transferencia Entrada',
        transfer_out: 'Transferencia Salida',
    } as Record<string, string>)[type] || type
}
</script>

<template>
    <ClinicLayout>
        <Head :title="`Kardex de Inventario — ${kardex.item.name}`" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0] print:hidden">
                <div class="flex items-center gap-3">
                    <Link 
                        href="/inventory"
                        class="p-2 text-[#505F76] hover:text-[#131B2E] hover:bg-white rounded-lg border border-[#E2E8F0] transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                                Kardex: {{ kardex.item.name }}
                            </h1>
                            <span v-if="kardex.item.sku" class="px-2 py-0.5 rounded-md text-xs font-mono font-bold bg-slate-100 text-slate-700">
                                SKU: {{ kardex.item.sku }}
                            </span>
                        </div>
                        <p class="text-xs text-[#505F76] mt-0.5">
                            Categoría: {{ kardex.item.category?.name || 'General' }} • Unidad: {{ kardex.item.unit }} • Stock Mínimo: {{ kardex.item.min_stock }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#BDC9C6] hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg transition shadow-xs"
                        @click="triggerPrint"
                    >
                        <Printer class="w-3.5 h-3.5" /> Imprimir Kardex
                    </button>
                </div>
            </div>

            <!-- Bento Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Stock Total Disponible</span>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-bold font-data-tabular" :class="kardex.total_stock <= kardex.item.min_stock ? 'text-[#BA1A1A]' : 'text-[#005C55]'">
                            {{ kardex.total_stock }}
                        </span>
                        <span class="text-xs text-[#505F76] font-medium">{{ kardex.item.unit }}</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Costo Unitario de Referencia</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">
                            {{ formatMoney(kardex.item.cost_price) }}
                        </span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Lotes Activos con Stock</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">
                            {{ kardex.item.batches.filter(b => b.current_quantity > 0).length }} lotes
                        </span>
                    </div>
                </div>
            </div>

            <!-- Active Batches Grid -->
            <div v-if="kardex.item.batches.length > 0" class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-3">
                <h3 class="font-section-title text-[#131B2E]">Lotes y Existencias por Bodega</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div 
                        v-for="b in kardex.item.batches" 
                        :key="b.id"
                        class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg text-xs space-y-1"
                    >
                        <div class="flex justify-between items-center">
                            <span class="font-mono font-bold text-[#005C55]">Lote: {{ b.batch_number }}</span>
                            <span class="font-bold text-[#131B2E]">{{ b.current_quantity }} {{ kardex.item.unit }}</span>
                        </div>
                        <p class="text-[11px] text-[#505F76]">Bodega: {{ b.warehouse?.name || 'Principal' }}</p>
                        <p v-if="b.expires_at" class="text-[11px] text-[#505F76]">Caducidad: {{ b.expires_at }}</p>
                        <p class="text-[11px] text-[#505F76]">Costo: {{ formatMoney(b.cost_per_unit) }}</p>
                    </div>
                </div>
            </div>

            <!-- Kardex Movements Ledger Table -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div>
                    <h3 class="font-section-title text-[#131B2E]">Libro Mayor de Movimientos (Kardex)</h3>
                    <p class="text-xs text-[#505F76]">Trazabilidad cronológica inmutable de entradas, salidas y saldos</p>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="kardex.movements.length > 0" class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Fecha y Hora</th>
                                <th class="px-4 py-2.5 font-semibold">Tipo</th>
                                <th class="px-4 py-2.5 font-semibold">Bodega / Lote</th>
                                <th class="px-4 py-2.5 font-semibold">Observación</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Cant. Anterior</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Movimiento</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Nuevo Stock</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Costo Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="m in kardex.movements" :key="m.id" class="hover:bg-[#F8FAFC] transition-colors h-11">
                                <td class="px-4 py-2 text-[#505F76] font-data-tabular">{{ m.created_at }}</td>
                                <td class="px-4 py-2 font-medium">
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                            m.quantity > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'
                                        ]"
                                    >
                                        {{ movementLabel(m.type) }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-[#131B2E]">
                                    <span>{{ m.warehouse?.name || 'Bodega' }}</span>
                                    <span v-if="m.batch" class="text-[10px] text-[#505F76] block font-mono">Lote: {{ m.batch.batch_number }}</span>
                                </td>
                                <td class="px-4 py-2 text-[#505F76] max-w-[200px] truncate">{{ m.notes || '-' }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular text-[#505F76]">{{ m.previous_stock }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular font-bold" :class="m.quantity > 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                                </td>
                                <td class="px-4 py-2 text-right font-data-tabular font-bold text-[#131B2E]">{{ m.new_stock }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular text-[#505F76]">{{ formatMoney(m.total_cost) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-8 text-center text-xs text-[#505F76]">
                        No hay movimientos registrados en el Kardex para este insumo.
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
