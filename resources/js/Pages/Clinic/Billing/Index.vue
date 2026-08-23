<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { ArrowLeft, Plus, CreditCard, ExternalLink, Link2, Receipt, RotateCcw, Trash2 } from 'lucide-vue-next'

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
const allocationPayment = ref<PaymentDetails | null>(null)
const refundPayment = ref<PaymentDetails | null>(null)

const paymentForm = useForm({
    cash_session_id: props.activeCashSession?.id || '',
    splits: [
        { method: props.activeCashSession ? 'cash' : 'transfer', amount: props.balanceDue > 0 ? props.balanceDue : 50, reference_code: '' },
    ],
    auto_allocate_charge_id: props.charges.find(c => c.balance_due > 0)?.id || '',
})

const chargeForm = useForm({
    concept: '',
    amount: 0,
    tax_amount: 0,
})

const allocationForm = useForm({
    patient_charge_id: '',
    amount: 0,
})

const refundForm = useForm({
    amount: 0,
    reason: '',
    cash_session_id: props.activeCashSession?.id || '',
})

const outstandingCharges = computed(() => props.charges.filter((charge) => charge.balance_due > 0))
const selectedAllocationCharge = computed(() => props.charges.find((charge) => charge.id === allocationForm.patient_charge_id) || null)
const allocationMaximum = computed(() => Math.min(
    allocationPayment.value?.unallocated_amount || 0,
    selectedAllocationCharge.value?.balance_due || 0,
))
const refundableMaximum = computed(() => Math.max(
    0,
    (refundPayment.value?.total_amount || 0) - (refundPayment.value?.refunded_amount || 0),
))
const paymentTotal = computed(() => paymentForm.splits.reduce((total, split) => total + Number(split.amount || 0), 0))
const paymentIncludesCash = computed(() => paymentForm.splits.some((split) => split.method === 'cash'))

function addPaymentSplit() {
    paymentForm.splits.push({ method: 'credit_card', amount: 0, reference_code: '' })
}

function removePaymentSplit(index: number) {
    if (paymentForm.splits.length === 1) return
    paymentForm.splits.splice(index, 1)
}

function openAllocation(payment: PaymentDetails) {
    const firstCharge = outstandingCharges.value[0]
    allocationPayment.value = payment
    allocationForm.clearErrors()
    allocationForm.patient_charge_id = firstCharge?.id || ''
    allocationForm.amount = Math.min(payment.unallocated_amount, firstCharge?.balance_due || 0)
}

function updateAllocationAmount() {
    allocationForm.amount = allocationMaximum.value
}

function submitAllocation() {
    if (!allocationPayment.value) return
    allocationForm.post(`/payments/${allocationPayment.value.id}/allocate`, {
        onSuccess: () => {
            allocationPayment.value = null
            allocationForm.reset()
        },
    })
}

function openRefund(payment: PaymentDetails) {
    refundPayment.value = payment
    refundForm.clearErrors()
    refundForm.amount = refundableMaximum.value
    refundForm.reason = ''
    refundForm.cash_session_id = ''
}

function submitRefund() {
    if (!refundPayment.value) return
    refundForm.post(`/payments/${refundPayment.value.id}/refund`, {
        onSuccess: () => {
            refundPayment.value = null
            refundForm.reset()
        },
    })
}

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
                            <td class="px-4 py-3"><Link :href="`/charges/${chg.id}`" class="inline-flex items-center gap-1.5 font-mono font-bold text-teal-400 hover:text-teal-300">{{ chg.charge_number }} <ExternalLink class="h-3 w-3" /></Link></td>
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
                    <div v-for="pay in payments" :key="pay.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex flex-wrap items-center justify-between gap-4 text-xs transition hover:border-teal-500/50 hover:bg-slate-900">
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
                        <div class="flex shrink-0 items-center gap-2">
                            <button v-if="pay.unallocated_amount > 0 && outstandingCharges.length" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-sky-500/40 px-3 py-2 font-bold text-sky-300 hover:bg-sky-500/10" @click="openAllocation(pay)"><Link2 class="h-3.5 w-3.5" /> Asignar</button>
                            <button v-if="pay.total_amount > pay.refunded_amount" type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-500/40 px-3 py-2 font-bold text-rose-300 hover:bg-rose-500/10" @click="openRefund(pay)"><RotateCcw class="h-3.5 w-3.5" /> Reembolsar</button>
                            <Link :href="`/payments/${pay.id}`" class="inline-flex items-center gap-1.5 rounded-lg border border-teal-500/40 px-3 py-2 font-bold text-teal-300 hover:bg-teal-500/10">Recibo <ExternalLink class="h-3.5 w-3.5" /></Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Allocation Modal -->
            <div v-if="allocationPayment" class="p-6 bg-slate-800 border border-sky-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div><h2 class="text-lg font-bold text-white">Asignar pago {{ allocationPayment.payment_number }}</h2><p class="text-xs text-slate-400">Disponible: ${{ allocationPayment.unallocated_amount.toFixed(2) }}</p></div>
                    <button class="text-slate-400 hover:text-white" @click="allocationPayment = null">×</button>
                </div>
                <form class="space-y-4" @submit.prevent="submitAllocation">
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Cargo pendiente</label><select v-model="allocationForm.patient_charge_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" @change="updateAllocationAmount"><option v-for="charge in outstandingCharges" :key="charge.id" :value="charge.id">{{ charge.charge_number }} · {{ charge.concept }} · ${{ charge.balance_due.toFixed(2) }}</option></select><p v-if="allocationForm.errors.patient_charge_id" class="mt-1 text-xs text-rose-300">{{ allocationForm.errors.patient_charge_id }}</p></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Monto a asignar</label><input v-model.number="allocationForm.amount" type="number" step="0.01" min="0.01" :max="allocationMaximum" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" /><p class="mt-1 text-[11px] text-slate-500">Máximo conciliable: ${{ allocationMaximum.toFixed(2) }}</p><p v-if="allocationForm.errors.amount" class="mt-1 text-xs text-rose-300">{{ allocationForm.errors.amount }}</p></div>
                    <div class="flex justify-end gap-2"><button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg" @click="allocationPayment = null">Cancelar</button><button type="submit" :disabled="allocationForm.processing || allocationMaximum <= 0" class="px-4 py-2 bg-sky-500 text-slate-950 text-xs font-bold rounded-lg disabled:opacity-50">Confirmar asignación</button></div>
                </form>
            </div>

            <!-- Refund Modal -->
            <div v-if="refundPayment" class="p-6 bg-slate-800 border border-rose-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <div><h2 class="text-lg font-bold text-white">Reembolsar {{ refundPayment.payment_number }}</h2><p class="text-xs text-slate-400">Reembolsable: ${{ refundableMaximum.toFixed(2) }}</p></div>
                    <button class="text-slate-400 hover:text-white" @click="refundPayment = null">×</button>
                </div>
                <form class="space-y-4" @submit.prevent="submitRefund">
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Monto del reembolso</label><input v-model.number="refundForm.amount" type="number" step="0.01" min="0.01" :max="refundableMaximum" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" /><p v-if="refundForm.errors.amount" class="mt-1 text-xs text-rose-300">{{ refundForm.errors.amount }}</p></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Motivo obligatorio</label><textarea v-model="refundForm.reason" rows="3" maxlength="255" required placeholder="Explique la causa para la bitácora financiera" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea><p v-if="refundForm.errors.reason" class="mt-1 text-xs text-rose-300">{{ refundForm.errors.reason }}</p></div>
                    <div><label class="block text-xs font-medium text-slate-400 mb-1">Salida de efectivo</label><select v-model="refundForm.cash_session_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"><option value="">No afecta efectivo físico</option><option v-if="activeCashSession" :value="activeCashSession.id">Registrar salida en la caja abierta</option></select><p class="mt-1 text-[11px] text-slate-500">Seleccione la caja solo cuando el dinero se devuelve físicamente en efectivo.</p><p v-if="refundForm.errors.cash_session_id" class="mt-1 text-xs text-rose-300">{{ refundForm.errors.cash_session_id }}</p></div>
                    <p class="border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">El reembolso reduce lo cobrado y queda registrado en auditoría. No elimina el pago ni el cargo original.</p>
                    <div class="flex justify-end gap-2"><button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg" @click="refundPayment = null">Cancelar</button><button type="submit" :disabled="refundForm.processing || refundableMaximum <= 0" class="px-4 py-2 bg-rose-500 text-white text-xs font-bold rounded-lg disabled:opacity-50">Confirmar reembolso</button></div>
                </form>
            </div>

            <!-- Payment Modal -->
            <div v-if="isPaymentModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Cobro al Paciente</h2>
                    <button class="text-slate-400 hover:text-white" @click="isPaymentModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitPayment">
                    <div class="flex items-center justify-between"><div><p class="text-xs font-bold uppercase tracking-wider text-teal-400">Desglose del cobro</p><p class="text-[11px] text-slate-500">La suma de todos los métodos formará el recibo.</p></div><button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-teal-500/40 px-3 py-2 text-xs font-bold text-teal-300 hover:bg-teal-500/10" @click="addPaymentSplit"><Plus class="h-3.5 w-3.5" /> Añadir método</button></div>

                    <div v-for="(split, index) in paymentForm.splits" :key="index" class="space-y-3 rounded-xl border border-slate-700 bg-slate-900/60 p-4">
                        <div class="flex items-center justify-between"><p class="text-xs font-bold text-white">Método {{ index + 1 }}</p><button v-if="paymentForm.splits.length > 1" type="button" class="text-rose-300 hover:text-rose-200" :aria-label="`Eliminar método ${index + 1}`" @click="removePaymentSplit(index)"><Trash2 class="h-4 w-4" /></button></div>
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div><label class="mb-1 block text-xs font-medium text-slate-400">Método de pago</label><select v-model="split.method" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white"><option value="cash">Efectivo</option><option value="credit_card">Tarjeta de crédito</option><option value="debit_card">Tarjeta de débito</option><option value="transfer">Transferencia bancaria</option><option value="zelle">Zelle</option><option value="insurance">Seguro dental</option></select></div>
                            <div><label class="mb-1 block text-xs font-medium text-slate-400">Monto ($)</label><input v-model.number="split.amount" type="number" step="0.01" min="0.01" required class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 font-mono text-xs text-white" /></div>
                        </div>
                        <div><label class="mb-1 block text-xs font-medium text-slate-400">Referencia / voucher</label><input v-model="split.reference_code" type="text" maxlength="100" placeholder="Opcional para efectivo" class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-xs text-white" /></div>
                        <p v-if="paymentForm.errors[`splits.${index}.amount`]" class="text-xs text-rose-300">{{ paymentForm.errors[`splits.${index}.amount`] }}</p>
                    </div>

                    <div class="flex items-center justify-between border-y border-slate-700 py-3"><span class="text-sm font-bold text-slate-300">Total del recibo</span><span class="font-mono text-xl font-black text-teal-400">${{ paymentTotal.toFixed(2) }}</span></div>
                    <p v-if="paymentIncludesCash && !activeCashSession" class="border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">Para aceptar efectivo primero debe abrir una sesión en <a href="/cash-registers" class="font-bold underline">Cajas & Arqueo</a>.</p>
                    <p v-if="paymentForm.errors.cash_session_id" class="text-xs text-rose-300">{{ paymentForm.errors.cash_session_id }}</p>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isPaymentModal = false">Cancelar</button>
                        <button type="submit" :disabled="paymentForm.processing || paymentTotal <= 0 || (paymentIncludesCash && !activeCashSession)" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400 disabled:cursor-not-allowed disabled:opacity-50">Registrar Pago</button>
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
