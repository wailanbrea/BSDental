<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ref, computed } from 'vue'
import { ArrowLeft, Building2, Download, Printer, RotateCcw } from 'lucide-vue-next'

interface Movement {
    id: string
    type: string
    amount: number
    payment_method: string
    concept: string
    created_at: string
    created_by?: { name: string }
}

interface CashSessionDetail {
    id: string
    status: 'open' | 'closing_review' | 'closed'
    opening_balance: number
    expected_cash: number
    counted_cash: number | null
    difference: number
    opened_at: string
    closed_at: string | null
    closing_notes?: string | null
    cash_register: {
        id: string
        name: string
        branch?: { name: string }
    }
    opened_by?: { name: string }
    closed_by?: { name: string }
    movements: Movement[]
}

const props = defineProps<{
    session: CashSessionDetail
    methodTotals: Record<string, number>
    totalIncome: number
    totalExpense: number
    canReopen: boolean
}>()

const isReopenModal = ref(false)
const selectedMethodFilter = ref('')
const searchTerm = ref('')

const reopenForm = useForm({
    reason: '',
})

function submitReopen() {
    reopenForm.post(appUrl(`/cash-sessions/${props.session.id}/reopen`), {
        onSuccess: () => {
            isReopenModal.value = false
            reopenForm.reset()
        },
    })
}

const filteredMovements = computed(() => {
    return props.session.movements.filter((m) => {
        const matchesMethod = !selectedMethodFilter.value || m.payment_method === selectedMethodFilter.value
        const matchesSearch = !searchTerm.value || 
            m.concept.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
            (m.created_by?.name || '').toLowerCase().includes(searchTerm.value.toLowerCase())
        return matchesMethod && matchesSearch
    })
})

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function methodLabel(method: string) {
    return ({
        cash: 'Efectivo',
        credit_card: 'Tarjeta Crédito',
        debit_card: 'Tarjeta Débito',
        transfer: 'Transferencia',
        zelle: 'Zelle',
        insurance: 'Seguro Médico',
        check: 'Cheque',
    } as Record<string, string>)[method] || method
}

function triggerPrint() {
    window.print()
}
</script>

<template>
    <ClinicLayout>
        <Head :title="`Detalle de Sesión de Caja — ${session.cash_register.name}`" />

        <div class="space-y-6">
            <!-- Top Nav & Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0] print:hidden">
                <div class="flex items-center gap-3">
                    <Link 
                        :href="appUrl('/cash-registers')" 
                        class="p-2 text-[#505F76] hover:text-[#131B2E] hover:bg-white rounded-lg border border-[#E2E8F0] transition"
                        title="Volver a Cajas"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                                Arqueo: {{ session.cash_register.name }}
                            </h1>
                            <span 
                                :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-bold border',
                                    session.status === 'open' 
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                        : 'bg-slate-100 text-slate-700 border-slate-300'
                                ]"
                            >
                                {{ session.status === 'open' ? 'Sesión Abierta' : 'Sesión Cerrada' }}
                            </span>
                        </div>
                        <p class="text-xs text-[#505F76] mt-0.5 flex items-center gap-1.5">
                            <Building2 class="w-3.5 h-3.5 text-[#005C55]" />
                            <span>{{ session.cash_register.branch?.name || 'Sede Principal' }}</span>
                            <span>•</span>
                            <span>Apertura: {{ session.opened_at }} ({{ session.opened_by?.name }})</span>
                        </p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2">
                    <button
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#BDC9C6] hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg transition shadow-xs"
                        @click="triggerPrint"
                    >
                        <Printer class="w-3.5 h-3.5" /> Imprimir Arqueo
                    </button>

                    <a
                        :href="`/cash-sessions/${session.id}/export`"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#BDC9C6] hover:bg-[#F8FAFC] text-[#005C55] font-semibold text-xs rounded-lg transition shadow-xs"
                    >
                        <Download class="w-3.5 h-3.5" /> Exportar CSV
                    </a>

                    <button
                        v-if="session.status === 'closed' && canReopen"
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-[#005C55] hover:bg-[#00504A] text-white font-semibold text-xs rounded-lg transition shadow-xs"
                        @click="isReopenModal = true"
                    >
                        <RotateCcw class="w-3.5 h-3.5" /> Reabrir Sesión
                    </button>
                </div>
            </div>

            <!-- Bento Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Card 1: Fondo Inicial -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Fondo inicial</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">
                            {{ formatMoney(session.opening_balance) }}
                        </span>
                    </div>
                </div>

                <!-- Card 2: Efectivo Esperado -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Efectivo esperado</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold text-[#005C55] font-data-tabular">
                            {{ formatMoney(session.expected_cash) }}
                        </span>
                    </div>
                </div>

                <!-- Card 3: Efectivo Contado Físico -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Efectivo contado</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular" :class="session.counted_cash !== null ? 'text-[#131B2E]' : 'text-[#505F76]'">
                            {{ session.counted_cash !== null ? formatMoney(session.counted_cash) : 'En turno' }}
                        </span>
                    </div>
                </div>

                <!-- Card 4: Descuadre / Diferencia -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Diferencia / Descuadre</span>
                    <div class="mt-2">
                        <span 
                            class="text-2xl font-bold font-data-tabular"
                            :class="session.difference === 0 ? 'text-emerald-700' : 'text-[#BA1A1A]'"
                        >
                            {{ formatMoney(session.difference) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Breakdown by Payment Method Grid -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <h3 class="font-section-title text-[#131B2E]">Desglose de Ingresos por Método de Pago</h3>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
                    <div 
                        v-for="(amount, method) in methodTotals" 
                        :key="method"
                        class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg"
                    >
                        <span class="font-label-caps text-[#505F76] text-[10px] block truncate">
                            {{ methodLabel(method) }}
                        </span>
                        <span class="font-data-tabular text-sm font-bold text-[#131B2E] mt-1 block">
                            {{ formatMoney(amount) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Notes & Audit Trail if applicable -->
            <div v-if="session.closing_notes" class="p-4 bg-[#F2F3FF] border border-[#BDC9C6] rounded-xl text-xs space-y-1">
                <span class="font-bold text-[#005C55] uppercase font-label-caps text-[10px]">Observaciones & Notas de Arqueo:</span>
                <p class="text-[#131B2E] whitespace-pre-line">{{ session.closing_notes }}</p>
            </div>

            <!-- Movements Table Section -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#E2E8F0]">
                    <div>
                        <h3 class="font-section-title text-[#131B2E]">Pista de Movimientos ({{ filteredMovements.length }})</h3>
                        <p class="text-xs text-[#505F76]">Cobros de pacientes, ingresos y egresos registrados en este turno</p>
                    </div>

                    <!-- Filters -->
                    <div class="flex items-center gap-3 print:hidden">
                        <input 
                            v-model="searchTerm"
                            type="text"
                            placeholder="Filtrar por concepto o usuario..."
                            class="px-3 py-1.5 text-xs bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg focus:outline-none focus:border-[#005C55]"
                        />

                        <select 
                            v-model="selectedMethodFilter"
                            class="px-3 py-1.5 text-xs bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E]"
                        >
                            <option value="">Todos los métodos</option>
                            <option value="cash">Efectivo</option>
                            <option value="credit_card">Tarjeta Crédito</option>
                            <option value="debit_card">Tarjeta Débito</option>
                            <option value="transfer">Transferencia</option>
                            <option value="zelle">Zelle</option>
                            <option value="insurance">Seguro</option>
                            <option value="check">Cheque</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="filteredMovements.length > 0" class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Hora</th>
                                <th class="px-4 py-2.5 font-semibold">Tipo</th>
                                <th class="px-4 py-2.5 font-semibold">Concepto</th>
                                <th class="px-4 py-2.5 font-semibold">Método</th>
                                <th class="px-4 py-2.5 font-semibold">Usuario</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="m in filteredMovements" :key="m.id" class="hover:bg-[#F8FAFC] transition-colors h-11">
                                <td class="px-4 py-2 text-[#505F76] font-data-tabular">{{ m.created_at?.substring(11, 16) }}</td>
                                <td class="px-4 py-2 font-medium">
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[10px] font-bold',
                                            m.amount > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'
                                        ]"
                                    >
                                        {{ m.type === 'patient_payment' ? 'Cobro Paciente' : (m.amount > 0 ? 'Ingreso' : 'Egreso') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-[#131B2E] font-medium">{{ m.concept }}</td>
                                <td class="px-4 py-2 text-[#505F76]">{{ methodLabel(m.payment_method) }}</td>
                                <td class="px-4 py-2 text-[#505F76]">{{ m.created_by?.name || 'Sistema' }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular font-bold" :class="m.amount > 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ m.amount > 0 ? '+' : '' }}{{ formatMoney(m.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-6 text-center text-xs text-[#505F76]">
                        No se encontraron movimientos con los filtros seleccionados.
                    </div>
                </div>
            </div>

            <!-- MODAL: Reopen Session -->
            <div v-if="isReopenModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50 print:hidden">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                        <h3 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <RotateCcw class="w-5 h-5 text-[#005C55]" />
                            <span>Reapertura Auditada de Sesión</span>
                        </h3>
                        <button class="text-[#505F76] hover:text-[#131B2E] font-bold text-lg" @click="isReopenModal = false">✕</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitReopen">
                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Motivo Obligatorio (10 - 500 caracteres) *</label>
                            <textarea 
                                v-model="reopenForm.reason"
                                rows="3"
                                required
                                minlength="10"
                                maxlength="500"
                                placeholder="Indique la justificación para reabrir esta sesión de caja cerrada..."
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E] focus:outline-none focus:border-[#005C55]"
                            ></textarea>
                            <p v-if="reopenForm.errors.reason" class="text-xs text-rose-600 mt-1">{{ reopenForm.errors.reason }}</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-[#E2E8F0]">
                            <button 
                                type="button" 
                                class="px-4 py-2 text-xs font-semibold text-[#505F76] hover:bg-[#F8FAFC] rounded-lg transition"
                                @click="isReopenModal = false"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="reopenForm.processing"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#005C55] hover:bg-[#00504A] rounded-lg transition shadow-xs"
                            >
                                {{ reopenForm.processing ? 'Reabriendo...' : 'Confirmar Reapertura' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
