<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { computed, ref } from 'vue'
import { AlertCircle, ArrowDownRight, ArrowUpRight, Building2, CreditCard, Lock, Plus, RotateCcw, Unlock } from 'lucide-vue-next'

interface MovementSummary {
    id: string
    type: string
    amount: number
    payment_method: string
    concept: string
    created_at: string
    created_by?: { name: string }
}

interface SessionSummary {
    id: string
    cash_register_id: string
    status: 'open' | 'closing_review' | 'closed'
    opening_balance: number
    expected_cash: number
    counted_cash: number | null
    difference: number
    opened_at: string
    closed_at: string | null
    closing_notes?: string | null
    opened_by?: { name: string }
    closed_by?: { name: string }
    cash_register?: {
        name: string
        branch?: { name: string }
    }
    movements?: MovementSummary[]
}

interface RegisterSummary {
    id: string
    name: string
    branch?: { id: string; name: string }
    active_session?: SessionSummary | null
    sessions: SessionSummary[]
}

const props = defineProps<{
    registers: RegisterSummary[]
    activeSessions: SessionSummary[]
    activeSession: SessionSummary | null
    canReopen: boolean
}>()

const isOpenModal = ref(false)
const isCloseModal = ref(false)
const isMovementModal = ref(false)
const isReopenModal = ref(false)

const selectedRegisterId = ref(props.registers[0]?.id || '')
const selectedSessionToClose = ref<SessionSummary | null>(props.activeSession)
const selectedSessionToReopen = ref<SessionSummary | null>(null)

const openForm = useForm({
    opening_balance: 100,
})

const closeForm = useForm({
    counted_cash: 0,
    closing_notes: '',
})

const movementForm = useForm({
    type: 'manual_income',
    amount: 0,
    payment_method: 'cash',
    concept: '',
})

const reopenForm = useForm({
    reason: '',
})

// Current focused session
const focusedSession = ref<SessionSummary | null>(props.activeSession || props.activeSessions[0] || null)

function selectSession(session: SessionSummary) {
    focusedSession.value = session
}

const cashMovements = computed(() => focusedSession.value?.movements?.filter((m) => m.payment_method === 'cash') || [])
const cashIncome = computed(() => cashMovements.value.filter((m) => m.amount > 0).reduce((total, m) => total + m.amount, 0))
const cashOutflow = computed(() => Math.abs(cashMovements.value.filter((m) => m.amount < 0).reduce((total, m) => total + m.amount, 0)))

function openModalForRegister(registerId: string) {
    selectedRegisterId.value = registerId
    isOpenModal.value = true
}

function openCloseModalForSession(session: SessionSummary) {
    selectedSessionToClose.value = session
    closeForm.counted_cash = session.expected_cash
    isCloseModal.value = true
}

function openReopenModalForSession(session: SessionSummary) {
    selectedSessionToReopen.value = session
    reopenForm.reset()
    isReopenModal.value = true
}

function submitOpen() {
    openForm.post(appUrl(`/cash-registers/${selectedRegisterId.value}/open`), {
        onSuccess: () => {
            isOpenModal.value = false
            openForm.reset()
        },
    })
}

function submitClose() {
    if (!selectedSessionToClose.value) return
    closeForm.post(appUrl(`/cash-sessions/${selectedSessionToClose.value.id}/close`), {
        onSuccess: () => {
            isCloseModal.value = false
            closeForm.reset()
        },
    })
}

function submitMovement() {
    if (!focusedSession.value) return
    movementForm.post(appUrl(`/cash-sessions/${focusedSession.value.id}/movements`), {
        onSuccess: () => {
            isMovementModal.value = false
            movementForm.reset()
        },
    })
}

function submitReopen() {
    if (!selectedSessionToReopen.value) return
    reopenForm.post(appUrl(`/cash-sessions/${selectedSessionToReopen.value.id}/reopen`), {
        onSuccess: () => {
            isReopenModal.value = false
            reopenForm.reset()
        },
    })
}

function movementMethodLabel(method: string) {
    return ({ 
        cash: 'Efectivo', 
        credit_card: 'Tarjeta Crédito', 
        debit_card: 'Tarjeta Débito', 
        transfer: 'Transferencia', 
        zelle: 'Zelle', 
        insurance: 'Seguro Médico', 
        check: 'Cheque' 
    } as Record<string, string>)[method] || method
}

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}
</script>

<template>
    <ClinicLayout>
        <Head title="Caja & Arqueo de Sesiones — BSDental" />

        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <h1 class="font-display-md text-2xl font-bold text-[#131B2E] flex items-center gap-2">
                        <CreditCard class="w-6 h-6 text-[#005C55]" />
                        <span>Cajas & Sesiones de Arqueo</span>
                    </h1>
                    <p class="text-xs text-[#505F76] mt-1">
                        Control multi-caja por sucursal, fondo inicial, arqueo ciego y reapertura auditada
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-[#005C55] hover:bg-[#00504A] text-white font-semibold rounded-lg text-xs transition shadow-xs"
                        @click="isOpenModal = true"
                    >
                        <Unlock class="w-4 h-4" /> Abrir Turno de Caja
                    </button>
                </div>
            </div>

            <!-- Active Open Sessions Banner / Selector -->
            <div v-if="activeSessions.length > 0" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-[#131B2E] flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>Sesiones Abiertas Actualmente ({{ activeSessions.length }})</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="s in activeSessions" 
                        :key="s.id"
                        :class="[
                            'p-5 rounded-xl border transition cursor-pointer flex flex-col justify-between shadow-xs',
                            focusedSession?.id === s.id 
                                ? 'bg-[#F2F3FF] border-[#005C55] ring-2 ring-[#005C55]/20' 
                                : 'bg-white border-[#E2E8F0] hover:border-[#BDC9C6]'
                        ]"
                        @click="selectSession(s)"
                    >
                        <div>
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        SESIÓN EN CURSO
                                    </span>
                                    <h3 class="font-bold text-sm text-[#131B2E] mt-2">{{ s.cash_register?.name || 'Caja' }}</h3>
                                    <p class="text-[11px] text-[#505F76] flex items-center gap-1 mt-0.5">
                                        <Building2 class="w-3.5 h-3.5" /> {{ s.cash_register?.branch?.name || 'Sede Principal' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-semibold text-[#505F76] uppercase block">Efectivo Esperado</span>
                                    <span class="font-data-tabular text-xl font-bold text-[#005C55]">{{ formatMoney(s.expected_cash) }}</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-[#E2E8F0] grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-[10px] text-[#505F76] block">Apertura por:</span>
                                    <span class="font-medium text-[#131B2E]">{{ s.opened_by?.name || 'Cajero' }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-[#505F76] block">Fondo inicial:</span>
                                    <span class="font-data-tabular font-semibold text-[#131B2E]">{{ formatMoney(s.opening_balance) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-[#E2E8F0] flex justify-between items-center">
                            <button 
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold text-[#005C55] bg-[#A3FAEF]/30 hover:bg-[#A3FAEF]/60 rounded-md transition"
                                @click.stop="isMovementModal = true; focusedSession = s"
                            >
                                + Movimiento
                            </button>
                            <button 
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold text-[#BA1A1A] bg-[#FFDAD6]/40 hover:bg-[#FFDAD6] rounded-md transition flex items-center gap-1"
                                @click.stop="openCloseModalForSession(s)"
                            >
                                <Lock class="w-3.5 h-3.5" /> Cerrar y Cuadrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Focused Session Detail & Movements -->
            <div v-if="focusedSession" class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#E2E8F0]">
                    <div>
                        <h3 class="font-section-title text-[#131B2E]">
                            Movimientos del Turno — {{ focusedSession.cash_register?.name || 'Caja' }}
                        </h3>
                        <p class="text-xs text-[#505F76]">
                            Abierto el {{ focusedSession.opened_at }} por {{ focusedSession.opened_by?.name }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-data-tabular">
                        <span class="text-emerald-700 font-semibold flex items-center gap-1">
                            <ArrowUpRight class="w-4 h-4" /> Ingresos Efvo: {{ formatMoney(cashIncome) }}
                        </span>
                        <span class="text-rose-700 font-semibold flex items-center gap-1">
                            <ArrowDownRight class="w-4 h-4" /> Egresos Efvo: {{ formatMoney(cashOutflow) }}
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="focusedSession.movements && focusedSession.movements.length > 0" class="w-full text-left border-collapse text-xs">
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
                            <tr v-for="m in focusedSession.movements" :key="m.id" class="hover:bg-[#F8FAFC] transition-colors h-10">
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
                                <td class="px-4 py-2 text-[#505F76]">{{ movementMethodLabel(m.payment_method) }}</td>
                                <td class="px-4 py-2 text-[#505F76]">{{ m.created_by?.name || 'Cajero' }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular font-bold" :class="m.amount > 0 ? 'text-emerald-700' : 'text-rose-700'">
                                    {{ m.amount > 0 ? '+' : '' }}{{ formatMoney(m.amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-6 text-center text-xs text-[#505F76]">
                        No hay movimientos registrados en esta sesión de caja todavía.
                    </div>
                </div>
            </div>

            <!-- Registers & History Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="reg in registers" :key="reg.id" class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-section-title text-[#131B2E]">{{ reg.name }}</h3>
                            <p class="text-xs text-[#505F76]">{{ reg.branch?.name || 'Sede Central' }}</p>
                        </div>
                        <div>
                            <span 
                                v-if="reg.active_session" 
                                class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200"
                            >
                                Abierta
                            </span>
                            <button 
                                v-else
                                class="px-3 py-1 bg-[#005C55] hover:bg-[#00504A] text-white text-xs font-semibold rounded-lg transition"
                                @click="openModalForRegister(reg.id)"
                            >
                                Abrir Caja
                            </button>
                        </div>
                    </div>

                    <!-- Recent Sessions in this Register -->
                    <div class="space-y-2 pt-2 border-t border-[#E2E8F0]">
                        <h4 class="font-label-caps text-[#505F76] text-[10px]">Historial de Sesiones</h4>
                        <div v-if="reg.sessions && reg.sessions.length > 0" class="space-y-2">
                            <div 
                                v-for="s in reg.sessions" 
                                :key="s.id"
                                class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg flex items-center justify-between text-xs"
                            >
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium text-[#131B2E]">{{ s.opened_at?.substring(0, 16) }}</span>
                                        <span 
                                            :class="[
                                                'px-1.5 py-0.2 rounded text-[9px] font-bold uppercase',
                                                s.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'
                                            ]"
                                        >
                                            {{ s.status }}
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-[#505F76] mt-0.5">
                                        Cerrado por: {{ s.closed_by?.name || s.opened_by?.name }}
                                        <span v-if="s.difference !== 0" class="text-rose-600 font-bold ml-1">
                                            (Descuadre: {{ formatMoney(s.difference) }})
                                        </span>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-data-tabular font-bold text-[#131B2E]">{{ formatMoney(s.expected_cash) }}</span>
                                    <!-- Reopen Action for closed sessions (FIN-01) -->
                                    <button 
                                        v-if="s.status === 'closed' && canReopen && !reg.active_session"
                                        class="p-1.5 text-[#005C55] hover:bg-[#F2F3FF] rounded transition"
                                        title="Reapertura auditada de turno cerrado"
                                        @click="openReopenModalForSession(s)"
                                    >
                                        <RotateCcw class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-[#505F76] py-2 text-center">
                            Sin historial de sesiones previas.
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODAL: Open Session -->
            <div v-if="isOpenModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                        <h3 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <Unlock class="w-5 h-5 text-[#005C55]" />
                            <span>Abrir Turno de Caja</span>
                        </h3>
                        <button class="text-[#505F76] hover:text-[#131B2E] font-bold text-lg" @click="isOpenModal = false">✕</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitOpen">
                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Caja Seleccionada</label>
                            <select 
                                v-model="selectedRegisterId"
                                class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E] focus:outline-none focus:border-[#005C55]"
                            >
                                <option v-for="r in registers" :key="r.id" :value="r.id">{{ r.name }} ({{ r.branch?.name }})</option>
                            </select>
                        </div>

                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Fondo Inicial en Efectivo ($)</label>
                            <input 
                                v-model.number="openForm.opening_balance"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-sm font-data-tabular text-[#131B2E] focus:outline-none focus:border-[#005C55]"
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-[#E2E8F0]">
                            <button 
                                type="button" 
                                class="px-4 py-2 text-xs font-semibold text-[#505F76] hover:bg-[#F8FAFC] rounded-lg transition"
                                @click="isOpenModal = false"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="openForm.processing"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#005C55] hover:bg-[#00504A] rounded-lg transition shadow-xs"
                            >
                                {{ openForm.processing ? 'Abriendo...' : 'Confirmar Apertura' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: Close Session (Blind Count) -->
            <div v-if="isCloseModal && selectedSessionToClose" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                        <h3 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <Lock class="w-5 h-5 text-[#BA1A1A]" />
                            <span>Cerrar Turno (Arqueo Ciego)</span>
                        </h3>
                        <button class="text-[#505F76] hover:text-[#131B2E] font-bold text-lg" @click="isCloseModal = false">✕</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitClose">
                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Efectivo Físico Contado ($) *</label>
                            <input 
                                v-model.number="closeForm.counted_cash"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-lg font-data-tabular font-bold text-[#131B2E] focus:outline-none focus:border-[#BA1A1A]"
                            />
                            <p class="text-[11px] text-[#505F76] mt-1">
                                Ingrese el recuento físico real del cajón de dinero.
                            </p>
                        </div>

                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Observaciones de Cierre</label>
                            <textarea 
                                v-model="closeForm.closing_notes"
                                rows="2"
                                placeholder="Notas sobre diferencias o justificaciones de descuadre..."
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E] focus:outline-none focus:border-[#005C55]"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-[#E2E8F0]">
                            <button 
                                type="button" 
                                class="px-4 py-2 text-xs font-semibold text-[#505F76] hover:bg-[#F8FAFC] rounded-lg transition"
                                @click="isCloseModal = false"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="closeForm.processing"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#BA1A1A] hover:bg-rose-700 rounded-lg transition shadow-xs"
                            >
                                {{ closeForm.processing ? 'Cerrando...' : 'Cerrar y Cuadrar Turno' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: Manual Movement -->
            <div v-if="isMovementModal && focusedSession" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                        <h3 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <Plus class="w-5 h-5 text-[#005C55]" />
                            <span>Registrar Movimiento Manual</span>
                        </h3>
                        <button class="text-[#505F76] hover:text-[#131B2E] font-bold text-lg" @click="isMovementModal = false">✕</button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitMovement">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="font-label-caps text-[#3E4947] block mb-1">Tipo</label>
                                <select 
                                    v-model="movementForm.type"
                                    class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E]"
                                >
                                    <option value="manual_income">Ingreso Manual</option>
                                    <option value="manual_expense">Egreso / Gasto</option>
                                </select>
                            </div>
                            <div>
                                <label class="font-label-caps text-[#3E4947] block mb-1">Método</label>
                                <select 
                                    v-model="movementForm.payment_method"
                                    class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E]"
                                >
                                    <option value="cash">Efectivo</option>
                                    <option value="credit_card">Tarjeta Crédito</option>
                                    <option value="debit_card">Tarjeta Débito</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="zelle">Zelle</option>
                                    <option value="check">Cheque</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Monto ($) *</label>
                            <input 
                                v-model.number="movementForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-sm font-data-tabular font-bold text-[#131B2E]"
                            />
                        </div>

                        <div>
                            <label class="font-label-caps text-[#3E4947] block mb-1">Concepto / Justificación *</label>
                            <input 
                                v-model="movementForm.concept"
                                type="text"
                                maxlength="255"
                                required
                                placeholder="Ej. Compra urgente de material clínico menor"
                                class="w-full px-3 py-2 bg-white border border-[#BDC9C6] rounded-lg text-xs text-[#131B2E]"
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-[#E2E8F0]">
                            <button 
                                type="button" 
                                class="px-4 py-2 text-xs font-semibold text-[#505F76] hover:bg-[#F8FAFC] rounded-lg transition"
                                @click="isMovementModal = false"
                            >
                                Cancelar
                            </button>
                            <button 
                                type="submit" 
                                :disabled="movementForm.processing"
                                class="px-4 py-2 text-xs font-semibold text-white bg-[#005C55] hover:bg-[#00504A] rounded-lg transition shadow-xs"
                            >
                                Registrar Movimiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- MODAL: Reopen Session (FIN-01) -->
            <div v-if="isReopenModal && selectedSessionToReopen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-50">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex justify-between items-center pb-2 border-b border-[#E2E8F0]">
                        <h3 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <RotateCcw class="w-5 h-5 text-[#005C55]" />
                            <span>Reapertura Auditada de Caja</span>
                        </h3>
                        <button class="text-[#505F76] hover:text-[#131B2E] font-bold text-lg" @click="isReopenModal = false">✕</button>
                    </div>

                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900 flex items-start gap-2">
                        <AlertCircle class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                        <span>Esta acción reabre el turno de caja cerrado para realizar ajustes necesarios. Quedará registrado en la pista de auditoría.</span>
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
                                placeholder="Indique la justificación técnica o administrativa para reabrir esta sesión..."
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
