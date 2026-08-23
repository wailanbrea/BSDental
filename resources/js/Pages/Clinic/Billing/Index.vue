<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { ArrowLeft, Plus, CreditCard, ExternalLink, Receipt } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface ChargeDetails {
    id: string
    charge_number: string
    concept: string
    amount: number
    tax_amount: number
    total_amount: number
    paid_amount: number
    balance_due: number
    status: 'pending' | 'partially_paid' | 'paid' | 'cancelled'
    created_at: string
}

interface PaymentDetails {
    id: string
    payment_number: string
    total_amount: number
    allocated_amount: number
    unallocated_amount: number
    refunded_amount: number
    status: string
    paid_at: string
}

interface SessionDetails {
    id: string
    status: string
}

const props = defineProps<{
    patient: PatientDetails
    charges: ChargeDetails[]
    payments: PaymentDetails[]
    totalCharged: number
    totalPaid: number
    balanceDue: number
    activeCashSession: SessionDetails | null
}>()

const isPaymentModal = ref(false)
const isChargeModal = ref(false)

const paymentForm = useForm({
    cash_session_id: props.activeCashSession?.id || '',
    splits: [
        { method: 'cash', amount: props.balanceDue > 0 ? props.balanceDue : 50, reference_code: '' },
    ],
    auto_allocate_charge_id: props.charges.find(c => c.balance_due > 0)?.id || '',
})

const chargeForm = useForm({
    concept: '',
    amount: 0,
    tax_amount: 0,
})

function submitPayment() {
    paymentForm.post(`/patients/${props.patient.id}/billing/payments`, {
        onSuccess: () => {
            isPaymentModal.value = false
        },
    })
}

function submitCharge() {
    chargeForm.post(`/patients/${props.patient.id}/billing/charges`, {
        onSuccess: () => {
            isChargeModal.value = false
            chargeForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="`Estado de Cuenta — ${patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <Receipt class="w-6 h-6 text-teal-400" /> Estado de Cuenta & Facturación
                    </h1>
                    <p class="text-sm text-slate-400">
                        Paciente: <span class="text-white font-semibold">{{ patient.full_name }}</span> ({{ patient.record_number }})
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a :href="`/patients/${patient.id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                    </a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-sm transition border border-slate-700"
                        @click="isChargeModal = true"
                    >
                        <Plus class="w-4 h-4" /> Nuevo Cargo
                    </button>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-lg text-sm transition"
                        @click="isPaymentModal = true"
                    >
                        <CreditCard class="w-4 h-4" /> Registrar Cobro
                    </button>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Facturado</span>
                    <div class="text-2xl font-mono font-black text-white">${{ totalCharged.toFixed(2) }}</div>
                </div>
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Cobrado</span>
                    <div class="text-2xl font-mono font-black text-emerald-400">${{ totalPaid.toFixed(2) }}</div>
                </div>
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Saldo Pendiente (CxC)</span>
                    <div class="text-2xl font-mono font-black text-amber-400">${{ balanceDue.toFixed(2) }}</div>
                </div>
            </div>

            <!-- Charges Table -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Cargos Clínicos Facturados</h2>

                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/80 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3">Nº Cargo</th>
                            <th class="px-4 py-3">Concepto</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-right">Pagado</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-xs">
                        <tr v-for="chg in charges" :key="chg.id" class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 font-mono font-bold text-teal-400">{{ chg.charge_number }}</td>
                            <td class="px-4 py-3 font-semibold text-white">{{ chg.concept }}</td>
                            <td class="px-4 py-3 text-right font-mono">${{ chg.total_amount.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-400">${{ chg.paid_amount.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-amber-400">${{ chg.balance_due.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    :class="[
                                        chg.status === 'paid' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                                        chg.status === 'partially_paid' ? 'bg-sky-500/20 text-sky-300 border-sky-500/30' :
                                        'bg-amber-500/20 text-amber-300 border-amber-500/30'
                                    ]"
                                    class="px-2.5 py-1 text-[11px] font-bold rounded-full border uppercase"
                                >
                                    {{ chg.status === 'paid' ? 'Pagado' : chg.status === 'partially_paid' ? 'Parcial' : 'Pendiente' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Payments History -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Historial de Recibos de Cobro</h2>

                <div class="space-y-2">
                    <Link v-for="pay in payments" :key="pay.id" :href="`/payments/${pay.id}`" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex items-center justify-between gap-4 text-xs transition hover:border-teal-500/50 hover:bg-slate-900">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-teal-400">{{ pay.payment_number }}</span>
                                <span class="text-white font-bold">${{ pay.total_amount.toFixed(2) }}</span>
                                <span class="px-2 py-0.5 bg-slate-800 text-slate-400 rounded text-[10px] uppercase font-bold">{{ pay.status }}</span>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-0.5">{{ pay.paid_at }}</div>
                        </div>

                        <div class="ml-auto text-right font-mono">
                            <span class="text-emerald-400 font-bold block">Asignado: ${{ pay.allocated_amount.toFixed(2) }}</span>
                            <span v-if="pay.unallocated_amount > 0" class="text-slate-400 text-[10px]">Sin asignar: ${{ pay.unallocated_amount.toFixed(2) }}</span>
                        </div>
                        <ExternalLink class="h-4 w-4 shrink-0 text-teal-400" aria-hidden="true" />
                    </Link>
                </div>
            </div>

            <!-- Payment Modal -->
            <div v-if="isPaymentModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Cobro al Paciente</h2>
                    <button class="text-slate-400 hover:text-white" @click="isPaymentModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitPayment">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Método de Pago</label>
                            <select v-model="paymentForm.splits[0].method" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                                <option value="cash">Efectivo</option>
                                <option value="credit_card">Tarjeta de Crédito</option>
                                <option value="debit_card">Tarjeta de Débito</option>
                                <option value="transfer">Transferencia Bancaria</option>
                                <option value="zelle">Zelle</option>
                                <option value="insurance">Seguro Dental</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Monto ($)</label>
                            <input v-model.number="paymentForm.splits[0].amount" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Referencia / Voucher (opcional)</label>
                        <input v-model="paymentForm.splits[0].reference_code" type="text" placeholder="Ej. Ref: 987654" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isPaymentModal = false">Cancelar</button>
                        <button type="submit" :disabled="paymentForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Registrar Pago</button>
                    </div>
                </form>
            </div>

            <!-- Charge Modal -->
            <div v-if="isChargeModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Generar Cargo Clínico</h2>
                    <button class="text-slate-400 hover:text-white" @click="isChargeModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitCharge">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Concepto del Cargo *</label>
                        <input v-model="chargeForm.concept" type="text" required placeholder="Ej. Consulta de Urgencia / Procedimiento" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Monto ($) *</label>
                            <input v-model.number="chargeForm.amount" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Impuestos ($)</label>
                            <input v-model.number="chargeForm.tax_amount" type="number" step="0.01" min="0" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isChargeModal = false">Cancelar</button>
                        <button type="submit" :disabled="chargeForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Crear Cargo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
