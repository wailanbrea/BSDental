<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { CreditCard, Lock, Unlock, ArrowDownRight, ArrowUpRight, Plus } from 'lucide-vue-next'

interface SessionSummary {
    id: string
    status: 'open' | 'closing_review' | 'closed'
    opening_balance: number
    expected_cash: number
    counted_cash: number | null
    difference: number
    opened_at: string
    closed_at: string | null
    opened_by?: { name: string }
    movements?: Array<{
        id: string
        type: string
        amount: number
        payment_method: string
        concept: string
        created_at: string
        created_by?: { name: string }
    }>
}

interface RegisterSummary {
    id: string
    name: string
    branch?: { name: string }
    sessions: SessionSummary[]
}

const props = defineProps<{
    registers: RegisterSummary[]
    activeSession: SessionSummary | null
}>()

const isOpenModal = ref(false)
const isCloseModal = ref(false)
const isMovementModal = ref(false)
const selectedRegisterId = ref(props.registers[0]?.id || '')

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

const cashMovements = computed(() => props.activeSession?.movements?.filter((movement) => movement.payment_method === 'cash') || [])
const cashIncome = computed(() => cashMovements.value.filter((movement) => movement.amount > 0).reduce((total, movement) => total + movement.amount, 0))
const cashOutflow = computed(() => Math.abs(cashMovements.value.filter((movement) => movement.amount < 0).reduce((total, movement) => total + movement.amount, 0)))

function submitOpen() {
    openForm.post(`/cash-registers/${selectedRegisterId.value}/open`, {
        onSuccess: () => {
            isOpenModal.value = false
        },
    })
}

function submitClose() {
    if (!props.activeSession) return
    closeForm.post(`/cash-sessions/${props.activeSession.id}/close`, {
        onSuccess: () => {
            isCloseModal.value = false
        },
    })
}

function submitMovement() {
    if (!props.activeSession) return
    movementForm.post(`/cash-sessions/${props.activeSession.id}/movements`, {
        onSuccess: () => {
            isMovementModal.value = false
            movementForm.reset()
        },
    })
}

function movementMethodLabel(method: string) {
    return ({ cash: 'Efectivo', credit_card: 'T. crédito', debit_card: 'T. débito', transfer: 'Transferencia', zelle: 'Zelle', insurance: 'Seguro', check: 'Cheque' } as Record<string, string>)[method] || method
}
</script>

<template>
    <Head title="Caja Chica & Arqueo de Sesiones — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <CreditCard class="w-6 h-6 text-teal-400" /> Cajas & Sesiones de Arqueo
                    </h1>
                    <p class="text-sm text-slate-400">Control de apertura/cierre de turnos de caja, fondo inicial y arqueo ciego</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
                    <button
                        v-if="!activeSession"
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-lg text-sm transition"
                        @click="isOpenModal = true"
                    >
                        <Unlock class="w-4 h-4" /> Abrir Turno de Caja
                    </button>
                    <button
                        v-else
                        class="flex items-center gap-2 px-4 py-2 bg-rose-500 hover:bg-rose-400 text-white font-bold rounded-lg text-sm transition"
                        @click="isCloseModal = true"
                    >
                        <Lock class="w-4 h-4" /> Cerrar Turno (Arqueo)
                    </button>
                </div>
            </div>

            <!-- Active Cash Session Banner -->
            <div v-if="activeSession" class="p-6 bg-slate-800/90 border border-teal-500/40 rounded-3xl space-y-4 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="px-3 py-1 bg-teal-500/10 text-teal-300 border border-teal-500/30 rounded-full font-mono text-xs font-bold">
                            SESIÓN ABIERTA
                        </span>
                        <h2 class="text-xl font-bold text-white mt-2">Caja Activa (Turno en Curso)</h2>
                        <p class="text-xs text-slate-400">Abierta el {{ activeSession.opened_at }}</p>
                    </div>

                    <div class="text-right">
                        <span class="text-xs text-slate-400 uppercase tracking-wider block">Efectivo Esperado</span>
                        <span class="text-3xl font-black text-teal-400 font-mono">${{ activeSession.expected_cash.toFixed(2) }}</span>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-slate-700 bg-slate-900/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Entradas de efectivo</p><p class="mt-1 font-mono text-lg font-bold text-emerald-400">${{ cashIncome.toFixed(2) }}</p></div>
                    <div class="rounded-xl border border-slate-700 bg-slate-900/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Salidas de efectivo</p><p class="mt-1 font-mono text-lg font-bold text-rose-400">${{ cashOutflow.toFixed(2) }}</p></div>
                    <button type="button" class="flex items-center justify-center gap-2 rounded-xl border border-teal-500/40 bg-teal-500/10 p-3 text-xs font-bold text-teal-300 hover:bg-teal-500/20" @click="isMovementModal = true"><Plus class="h-4 w-4" /> Registrar movimiento</button>
                </div>

                <!-- Live Session Movements -->
                <div class="pt-4 border-t border-slate-700/60 space-y-2">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Movimientos del Turno</h3>
                    <div v-for="m in activeSession.movements" :key="m.id" class="p-3 bg-slate-900/80 rounded-xl flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <ArrowUpRight v-if="m.amount > 0" class="w-4 h-4 text-emerald-400" />
                            <ArrowDownRight v-else class="w-4 h-4 text-rose-400" />
                            <div><span class="text-white font-semibold">{{ m.concept }}</span><p class="mt-0.5 text-[10px] text-slate-500">{{ movementMethodLabel(m.payment_method) }} · {{ m.created_by?.name || 'Usuario de caja' }}</p></div>
                        </div>
                        <span :class="[m.amount > 0 ? 'text-emerald-400' : 'text-rose-400']" class="font-mono font-bold">
                            ${{ m.amount.toFixed(2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Manual Movement Modal -->
            <div v-if="isMovementModal && activeSession" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between"><div><h2 class="text-lg font-bold text-white">Registrar movimiento manual</h2><p class="text-xs text-slate-400">Quedará vinculado a la sesión abierta y a la auditoría.</p></div><button class="text-slate-400 hover:text-white" @click="isMovementModal = false">×</button></div>
                <form class="space-y-4" @submit.prevent="submitMovement">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div><label class="mb-1 block text-xs font-medium text-slate-400">Tipo</label><select v-model="movementForm.type" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white"><option value="manual_income">Ingreso manual</option><option value="manual_expense">Egreso manual</option></select></div>
                        <div><label class="mb-1 block text-xs font-medium text-slate-400">Método</label><select v-model="movementForm.payment_method" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white"><option value="cash">Efectivo</option><option value="credit_card">Tarjeta de crédito</option><option value="debit_card">Tarjeta de débito</option><option value="transfer">Transferencia</option><option value="zelle">Zelle</option><option value="check">Cheque</option><option value="insurance">Seguro</option></select></div>
                    </div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-400">Monto</label><input v-model.number="movementForm.amount" type="number" min="0.01" step="0.01" required class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 font-mono text-xs text-white" /><p v-if="movementForm.errors.amount" class="mt-1 text-xs text-rose-300">{{ movementForm.errors.amount }}</p></div>
                    <div><label class="mb-1 block text-xs font-medium text-slate-400">Concepto / justificación</label><input v-model="movementForm.concept" type="text" maxlength="255" required placeholder="Ej. Fondo adicional autorizado" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white" /><p v-if="movementForm.errors.concept" class="mt-1 text-xs text-rose-300">{{ movementForm.errors.concept }}</p></div>
                    <p class="border border-slate-700 bg-slate-900/60 p-3 text-xs text-slate-400">Solo los movimientos marcados como efectivo cambian el efectivo esperado del arqueo.</p>
                    <div class="flex justify-end gap-2"><button type="button" class="rounded-lg bg-slate-700 px-4 py-2 text-xs font-medium text-slate-300" @click="isMovementModal = false">Cancelar</button><button type="submit" :disabled="movementForm.processing" class="rounded-lg bg-teal-500 px-4 py-2 text-xs font-bold text-slate-950 disabled:opacity-50">Registrar movimiento</button></div>
                </form>
            </div>

            <!-- Registers List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="reg in registers" :key="reg.id" class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-bold text-white text-base">{{ reg.name }}</h2>
                            <p class="text-xs text-slate-400">Sede: {{ reg.branch?.name || 'Central' }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Últimas Sesiones</h3>
                        <div v-for="s in reg.sessions" :key="s.id" class="p-3 bg-slate-900/80 rounded-xl flex items-center justify-between text-xs">
                            <div>
                                <span :class="[s.status === 'open' ? 'text-teal-400 font-bold' : 'text-slate-400']" class="block font-mono">
                                    {{ s.opened_at.substring(0, 16) }} ({{ s.status }})
                                </span>
                                <span v-if="s.difference !== 0" class="text-rose-400 text-[10px]">
                                    Descuadre: ${{ s.difference.toFixed(2) }}
                                </span>
                            </div>
                            <span class="font-mono text-white font-bold">${{ s.expected_cash.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Open Session Modal -->
            <div v-if="isOpenModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Abrir Turno de Caja</h2>
                    <button class="text-slate-400 hover:text-white" @click="isOpenModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitOpen">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Caja</label>
                        <select v-model="selectedRegisterId" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option v-for="r in registers" :key="r.id" :value="r.id">{{ r.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Fondo Inicial en Efectivo ($)</label>
                        <input v-model.number="openForm.opening_balance" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isOpenModal = false">Cancelar</button>
                        <button type="submit" :disabled="openForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Abrir Caja</button>
                    </div>
                </form>
            </div>

            <!-- Close Session Modal (Blind Count) -->
            <div v-if="isCloseModal && activeSession" class="p-6 bg-slate-800 border border-rose-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Cerrar Turno de Caja (Arqueo Ciego)</h2>
                    <button class="text-slate-400 hover:text-white" @click="isCloseModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitClose">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Efectivo Físico Contado ($) *</label>
                        <input v-model.number="closeForm.counted_cash" type="number" step="0.01" min="0" required placeholder="Ingrese el conteo físico real" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono text-lg font-bold" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Notas de Cierre / Justificación</label>
                        <textarea v-model="closeForm.closing_notes" rows="2" placeholder="Observaciones sobre diferencias de arqueo si las hubiere" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isCloseModal = false">Cancelar</button>
                        <button type="submit" :disabled="closeForm.processing" class="px-4 py-2 bg-rose-500 text-white text-xs font-bold rounded-lg hover:bg-rose-400">Cerrar y Cuadrar Turno</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
