<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { 
    ArrowLeft, 
    Plus, 
    CreditCard, 
    ExternalLink, 
    Link2, 
    Receipt, 
    RotateCcw, 
    Trash2, 
    DollarSign, 
    FileText, 
    X,
    AlertCircle,
    CheckCircle2
} from 'lucide-vue-next'

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

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

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
    <ClinicLayout>
        <Head :title="`Estado de Cuenta — ${patient.full_name}`" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div class="flex items-center gap-3">
                    <Link 
                        :href="`/patients/${patient.id}`"
                        class="p-2 text-[#505F76] hover:text-[#131B2E] hover:bg-white rounded-lg border border-[#E2E8F0] transition"
                        title="Volver a Ficha 360"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                                Facturación & Cobros: {{ patient.full_name }}
                            </h1>
                            <span class="px-2 py-0.5 rounded-md text-xs font-mono font-bold bg-[#005C55]/10 text-[#005C55]">
                                {{ patient.record_number }}
                            </span>
                        </div>
                        <p class="text-xs text-[#505F76] mt-0.5">
                            Gestión de cargos clínicos, conciliación de pagos y notas de crédito
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="`/patients/${patient.id}/billing/statement`"
                        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg border border-[#BDC9C6] transition shadow-xs"
                    >
                        <FileText class="w-3.5 h-3.5 text-[#505F76]" /> Estado de Cuenta
                    </Link>
                    <button
                        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg border border-[#BDC9C6] transition shadow-xs"
                        @click="isChargeModal = true"
                    >
                        <Plus class="w-3.5 h-3.5 text-[#005C55]" /> Nuevo Cargo
                    </button>
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isPaymentModal = true"
                    >
                        <CreditCard class="w-3.5 h-3.5" /> Registrar Cobro
                    </button>
                </div>
            </div>

            <!-- Financial Summary Bento Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Total Facturado</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">{{ formatMoney(totalCharged) }}</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Total Cobrado</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular text-emerald-700">{{ formatMoney(totalPaid) }}</span>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <span class="text-xs font-semibold text-[#505F76]">Saldo Pendiente (CxC)</span>
                    <div class="mt-2">
                        <span class="text-2xl font-bold font-data-tabular" :class="balanceDue > 0 ? 'text-[#BA1A1A]' : 'text-[#005C55]'">
                            {{ formatMoney(balanceDue) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Charges Table -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                <div class="px-5 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
                    <div>
                        <h2 class="font-section-title text-[#131B2E]">Cargos Clínicos Facturados</h2>
                        <p class="text-xs text-[#505F76]">Historial de procedimientos y cargos emitidos al paciente</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Nº Cargo</th>
                                <th class="px-4 py-3 font-semibold">Concepto</th>
                                <th class="px-4 py-3 font-semibold text-right">Total</th>
                                <th class="px-4 py-3 font-semibold text-right">Pagado</th>
                                <th class="px-4 py-3 font-semibold text-right">Saldo</th>
                                <th class="px-4 py-3 font-semibold text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="chg in charges" :key="chg.id" class="hover:bg-[#F8FAFC] transition-colors h-12">
                                <td class="px-5 py-2.5">
                                    <Link :href="`/charges/${chg.id}`" class="inline-flex items-center gap-1.5 font-mono font-bold text-[#005C55] hover:underline">
                                        {{ chg.charge_number }} <ExternalLink class="h-3 w-3" />
                                    </Link>
                                </td>
                                <td class="px-4 py-2.5 font-medium text-[#131B2E]">{{ chg.concept }}</td>
                                <td class="px-4 py-2.5 text-right font-data-tabular text-[#131B2E]">{{ formatMoney(chg.total_amount) }}</td>
                                <td class="px-4 py-2.5 text-right font-data-tabular text-emerald-700 font-semibold">{{ formatMoney(chg.paid_amount) }}</td>
                                <td class="px-4 py-2.5 text-right font-data-tabular font-bold" :class="chg.balance_due > 0 ? 'text-[#BA1A1A]' : 'text-slate-500'">
                                    {{ formatMoney(chg.balance_due) }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <span
                                        :class="[
                                            'px-2.5 py-0.5 rounded-full text-[11px] font-semibold border inline-block',
                                            chg.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                                            chg.status === 'partially_paid' ? 'bg-blue-50 text-blue-700 border-blue-200' :
                                            'bg-amber-50 text-amber-800 border-amber-200'
                                        ]"
                                    >
                                        {{ chg.status === 'paid' ? 'Pagado' : chg.status === 'partially_paid' ? 'Parcial' : 'Pendiente' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="charges.length === 0">
                                <td colspan="6" class="px-5 py-8 text-center text-xs text-[#505F76]">
                                    No hay cargos registrados para este paciente.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments History -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h2 class="font-section-title text-[#131B2E]">Historial de Recibos de Cobro</h2>
                        <p class="text-xs text-[#505F76]">Recibos emitidos, conciliaciones y opciones de reembolso</p>
                    </div>
                </div>

                <div class="space-y-2.5">
                    <div 
                        v-for="pay in payments" 
                        :key="pay.id" 
                        class="p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl flex flex-wrap items-center justify-between gap-4 text-xs hover:border-[#BDC9C6] transition"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold text-[#005C55] text-sm">{{ pay.payment_number }}</span>
                                <span class="font-bold text-[#131B2E] text-sm">{{ formatMoney(pay.total_amount) }}</span>
                                <span class="px-2 py-0.5 bg-white border border-[#E2E8F0] text-[#505F76] rounded text-[10px] uppercase font-bold">
                                    {{ pay.status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-[#505F76] mt-0.5 font-mono">{{ pay.paid_at }}</div>
                        </div>

                        <div class="ml-auto text-right font-mono">
                            <span class="text-emerald-700 font-bold block">Asignado: {{ formatMoney(pay.allocated_amount) }}</span>
                            <span v-if="pay.unallocated_amount > 0" class="text-amber-800 text-[10px] font-semibold">
                                Sin asignar: {{ formatMoney(pay.unallocated_amount) }}
                            </span>
                        </div>
                        
                        <div class="flex shrink-0 items-center gap-2">
                            <button 
                                v-if="pay.unallocated_amount > 0 && outstandingCharges.length" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-white px-3 py-1.5 font-semibold text-blue-700 hover:bg-blue-50 transition shadow-2xs" 
                                @click="openAllocation(pay)"
                            >
                                <Link2 class="h-3.5 w-3.5" /> Asignar
                            </button>
                            <button 
                                v-if="pay.total_amount > pay.refunded_amount" 
                                type="button" 
                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white px-3 py-1.5 font-semibold text-[#BA1A1A] hover:bg-rose-50 transition shadow-2xs" 
                                @click="openRefund(pay)"
                            >
                                <RotateCcw class="h-3.5 w-3.5" /> Reembolsar
                            </button>
                            <Link 
                                :href="`/payments/${pay.id}`" 
                                class="inline-flex items-center gap-1.5 rounded-lg border border-[#BDC9C6] bg-white px-3 py-1.5 font-semibold text-[#131B2E] hover:bg-[#F8FAFC] transition shadow-2xs"
                            >
                                Recibo <ExternalLink class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </div>

                    <div v-if="payments.length === 0" class="p-6 text-center text-xs text-[#505F76]">
                        No hay cobros registrados para este paciente.
                    </div>
                </div>
            </div>

            <!-- Allocation Modal -->
            <div v-if="allocationPayment" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="allocationPayment = null">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div>
                            <h2 class="text-sm font-bold text-[#131B2E]">Asignar pago {{ allocationPayment.payment_number }}</h2>
                            <p class="text-xs text-[#505F76]">Monto disponible: {{ formatMoney(allocationPayment.unallocated_amount) }}</p>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="allocationPayment = null">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitAllocation">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Cargo pendiente *</label>
                            <select v-model="allocationForm.patient_charge_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" @change="updateAllocationAmount">
                                <option v-for="charge in outstandingCharges" :key="charge.id" :value="charge.id">
                                    {{ charge.charge_number }} • {{ charge.concept }} • Saldo: {{ formatMoney(charge.balance_due) }}
                                </option>
                            </select>
                            <p v-if="allocationForm.errors.patient_charge_id" class="mt-1 text-xs text-[#BA1A1A]">{{ allocationForm.errors.patient_charge_id }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Monto a asignar ($) *</label>
                            <input v-model.number="allocationForm.amount" type="number" step="0.01" min="0.01" :max="allocationMaximum" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                            <p class="mt-1 text-[11px] text-[#505F76]">Máximo conciliable: {{ formatMoney(allocationMaximum) }}</p>
                            <p v-if="allocationForm.errors.amount" class="mt-1 text-xs text-[#BA1A1A]">{{ allocationForm.errors.amount }}</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="allocationPayment = null">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="allocationForm.processing || allocationMaximum <= 0" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Confirmar asignación
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Refund Modal -->
            <div v-if="refundPayment" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="refundPayment = null">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div>
                            <h2 class="text-sm font-bold text-[#BA1A1A]">Reembolsar {{ refundPayment.payment_number }}</h2>
                            <p class="text-xs text-[#505F76]">Máximo reembolsable: {{ formatMoney(refundableMaximum) }}</p>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="refundPayment = null">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitRefund">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Monto del reembolso ($) *</label>
                            <input v-model.number="refundForm.amount" type="number" step="0.01" min="0.01" :max="refundableMaximum" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                            <p v-if="refundForm.errors.amount" class="mt-1 text-xs text-[#BA1A1A]">{{ refundForm.errors.amount }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Motivo obligatorio *</label>
                            <textarea v-model="refundForm.reason" rows="2" maxlength="255" required placeholder="Explique la causa para la bitácora financiera..." class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]"></textarea>
                            <p v-if="refundForm.errors.reason" class="mt-1 text-xs text-[#BA1A1A]">{{ refundForm.errors.reason }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Salida de efectivo físico</label>
                            <select v-model="refundForm.cash_session_id" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option value="">No afecta efectivo físico (Transferencia / Tarjeta)</option>
                                <option v-if="activeCashSession" :value="activeCashSession.id">Registrar egreso de efectivo en la caja abierta</option>
                            </select>
                            <p v-if="refundForm.errors.cash_session_id" class="mt-1 text-xs text-[#BA1A1A]">{{ refundForm.errors.cash_session_id }}</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="refundPayment = null">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="refundForm.processing || refundableMaximum <= 0" class="px-4 py-2 bg-[#BA1A1A] hover:bg-rose-700 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Confirmar Reembolso
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payment Modal -->
            <div v-if="isPaymentModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isPaymentModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <CreditCard class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Registrar Cobro al Paciente</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isPaymentModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitPayment">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-[#005C55]">Desglose de Pago</p>
                                <p class="text-[11px] text-[#505F76]">Soporte para cobros divididos en múltiples métodos</p>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-[#005C55] border border-[#005C55]/30 rounded-lg hover:bg-[#005C55]/10" @click="addPaymentSplit">
                                <Plus class="h-3.5 w-3.5" /> Añadir método
                            </button>
                        </div>

                        <div v-for="(split, index) in paymentForm.splits" :key="index" class="space-y-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-bold text-[#131B2E]">Método {{ index + 1 }}</p>
                                <button v-if="paymentForm.splits.length > 1" type="button" class="text-[#BA1A1A] hover:text-rose-700" :aria-label="`Eliminar método ${index + 1}`" @click="removePaymentSplit(index)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[#505F76]">Método de pago</label>
                                    <select v-model="split.method" class="w-full rounded-lg border border-[#BDC9C6] bg-white px-3 py-2 text-xs text-[#131B2E] focus:border-[#005C55]">
                                        <option value="cash">Efectivo</option>
                                        <option value="credit_card">Tarjeta de crédito</option>
                                        <option value="debit_card">Tarjeta de débito</option>
                                        <option value="transfer">Transferencia bancaria</option>
                                        <option value="zelle">Zelle</option>
                                        <option value="insurance">Seguro dental</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-[#505F76]">Monto ($) *</label>
                                    <input v-model.number="split.amount" type="number" step="0.01" min="0.01" required class="w-full rounded-lg border border-[#BDC9C6] bg-white px-3 py-2 font-mono text-xs text-[#131B2E] focus:border-[#005C55]" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-[#505F76]">Referencia / Voucher</label>
                                <input v-model="split.reference_code" type="text" maxlength="100" placeholder="Número de confirmación o recibo físico" class="w-full rounded-lg border border-[#BDC9C6] bg-white px-3 py-2 text-xs text-[#131B2E] focus:border-[#005C55]" />
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-y border-[#E2E8F0] py-3">
                            <span class="text-xs font-bold text-[#505F76]">Total del recibo:</span>
                            <span class="font-data-tabular text-xl font-bold text-[#005C55]">{{ formatMoney(paymentTotal) }}</span>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isPaymentModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="paymentForm.processing || paymentTotal <= 0 || (paymentIncludesCash && !activeCashSession)" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Registrar Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Charge Modal -->
            <div v-if="isChargeModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isChargeModal = false">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Plus class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Generar Cargo Clínico</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isChargeModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitCharge">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Concepto del Cargo *</label>
                            <input v-model="chargeForm.concept" type="text" required placeholder="Ej. Profilaxis + Restauración Pieza 16" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="grid grid-cols-2 gap-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-[#505F76] mb-1">Monto ($) *</label>
                                <input v-model.number="chargeForm.amount" type="number" step="0.01" min="0.01" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#505F76] mb-1">Impuestos ($)</label>
                                <input v-model.number="chargeForm.tax_amount" type="number" step="0.01" min="0" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isChargeModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="chargeForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Crear Cargo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
