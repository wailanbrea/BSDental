<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import type { PageProps } from '@/types'
import { ArrowLeft, CheckCircle2, CircleDollarSign, Printer, ReceiptText, RotateCcw } from 'lucide-vue-next'
import { computed } from 'vue'

interface PatientSummary {
    id: string
    record_number: string
    full_name: string
    identification_number: string | null
    phone: string | null
    email: string | null
}

interface PaymentSplit {
    id: string
    method: string
    amount: number
    reference_code: string | null
}

interface Allocation {
    id: string
    amount: number
    allocated_at: string
    charge: {
        id: string
        charge_number: string
        concept: string
        total_amount: number
        status: string
    }
}

interface Refund {
    id: string
    amount: number
    reason: string
    refunded_at: string
    created_by: string | null
}

interface PaymentReceipt {
    id: string
    payment_number: string
    total_amount: number
    allocated_amount: number
    unallocated_amount: number
    refunded_amount: number
    net_amount: number
    status: string
    paid_at: string
    created_at: string
    created_by: string | null
    cash_register: string | null
    patient: PatientSummary
    splits: PaymentSplit[]
    allocations: Allocation[]
    refunds: Refund[]
}

type ReceiptPageProps = PageProps<{
    clinic?: { trade_name?: string | null; name?: string | null; clinic_name?: string | null }
}>

defineProps<{ payment: PaymentReceipt }>()
const page = usePage<ReceiptPageProps>()
const clinicName = computed(() => page.props.clinic?.trade_name || page.props.clinic?.name || 'BSDental')

const paymentMethods: Record<string, string> = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    credit_card: 'Tarjeta de crédito',
    debit_card: 'Tarjeta de débito',
    transfer: 'Transferencia bancaria',
    zelle: 'Zelle',
    insurance: 'Seguro dental',
}

function formatMoney(value: number) {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value)
}

function formatDate(value: string, includeTime = true) {
    return new Intl.DateTimeFormat('es-DO', includeTime
        ? { dateStyle: 'long', timeStyle: 'short' }
        : { dateStyle: 'long' }).format(new Date(value))
}

function printReceipt() {
    window.print()
}
</script>

<template>
    <Head :title="`Recibo ${payment.payment_number}`" />

    <ClinicLayout>
        <div class="receipt-screen mx-auto max-w-5xl space-y-5">
            <div class="no-print flex flex-wrap items-center justify-between gap-3">
                <Link :href="`/patients/${payment.patient.id}/billing`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55] transition hover:border-[#007D73]">
                    <ArrowLeft class="h-4 w-4" /> Estado de cuenta
                </Link>
                <button type="button" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white transition hover:bg-[#004A45]" @click="printReceipt">
                    <Printer class="h-4 w-4" /> Imprimir recibo
                </button>
            </div>

            <article class="receipt-paper border border-[#BDC9C6] bg-white shadow-sm">
                <header class="receipt-header flex flex-col gap-5 border-b-2 border-[#007D73] p-6 sm:flex-row sm:items-start sm:justify-between sm:p-8">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="grid h-11 w-11 place-items-center bg-[#005C55] text-white"><ReceiptText class="h-6 w-6" /></div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#006B63]">{{ clinicName }}</p>
                                <h1 class="text-2xl font-bold text-[#131B2E]">Recibo de pago</h1>
                            </div>
                        </div>
                        <p class="mt-4 max-w-lg text-sm text-[#52615E]">Comprobante del pago recibido y su aplicación a la cuenta del paciente.</p>
                    </div>
                    <div class="border border-[#B7D9D4] bg-[#F1FAF8] px-5 py-4 sm:text-right">
                        <p class="font-mono text-xl font-bold text-[#005C55]">{{ payment.payment_number }}</p>
                        <p class="mt-1 text-xs text-[#52615E]">{{ formatDate(payment.paid_at) }}</p>
                        <span class="mt-3 inline-flex items-center gap-1.5 bg-[#D8ECE9] px-2.5 py-1 text-xs font-bold text-[#006B63]">
                            <CheckCircle2 class="h-3.5 w-3.5" /> Pago registrado
                        </span>
                    </div>
                </header>

                <div class="grid gap-0 border-b border-[#D8E0DE] md:grid-cols-2">
                    <section class="p-6 sm:p-8 md:border-r md:border-[#D8E0DE]">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[#667085]">Recibido de</h2>
                        <p class="mt-3 text-lg font-bold text-[#131B2E]">{{ payment.patient.full_name }}</p>
                        <p class="mt-1 font-mono text-sm text-[#006B63]">{{ payment.patient.record_number }}</p>
                        <div class="mt-3 space-y-1 text-sm text-[#52615E]">
                            <p v-if="payment.patient.identification_number">Identificación: {{ payment.patient.identification_number }}</p>
                            <p v-if="payment.patient.phone">Teléfono: {{ payment.patient.phone }}</p>
                            <p v-if="payment.patient.email">{{ payment.patient.email }}</p>
                        </div>
                    </section>
                    <section class="p-6 sm:p-8">
                        <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[#667085]">Registro</h2>
                        <dl class="mt-3 grid grid-cols-[130px_1fr] gap-y-2 text-sm">
                            <dt class="text-[#667085]">Registrado por</dt><dd class="font-semibold text-[#131B2E]">{{ payment.created_by || 'Usuario de la clínica' }}</dd>
                            <dt class="text-[#667085]">Caja</dt><dd class="font-semibold text-[#131B2E]">{{ payment.cash_register || 'Sin sesión de caja' }}</dd>
                            <dt class="text-[#667085]">Estado contable</dt><dd class="font-mono text-xs font-bold uppercase text-[#006B63]">{{ payment.status.replaceAll('_', ' ') }}</dd>
                        </dl>
                    </section>
                </div>

                <section class="p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-[#131B2E]"><CircleDollarSign class="h-5 w-5 text-[#007D73]" /> Métodos de pago</h2>
                        <p class="font-mono text-lg font-bold text-[#131B2E]">{{ formatMoney(payment.total_amount) }}</p>
                    </div>
                    <div class="mt-4 overflow-hidden border border-[#D8E0DE]">
                        <div v-for="split in payment.splits" :key="split.id" class="grid grid-cols-[1fr_auto] gap-3 border-b border-[#E4E9E7] p-4 last:border-0 sm:grid-cols-[1fr_1fr_auto]">
                            <p class="font-semibold text-[#131B2E]">{{ paymentMethods[split.method] || split.method }}</p>
                            <p class="hidden font-mono text-sm text-[#667085] sm:block">{{ split.reference_code || 'Sin referencia' }}</p>
                            <p class="font-mono font-bold text-[#131B2E]">{{ formatMoney(split.amount) }}</p>
                            <p v-if="split.reference_code" class="col-span-2 font-mono text-xs text-[#667085] sm:hidden">Ref. {{ split.reference_code }}</p>
                        </div>
                    </div>
                </section>

                <section class="border-t border-[#D8E0DE] p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-[#131B2E]">Aplicación del pago</h2>
                    <p class="mt-1 text-sm text-[#667085]">Cargos de la cuenta cubiertos con este recibo.</p>
                    <div v-if="payment.allocations.length" class="mt-4 overflow-hidden border border-[#D8E0DE]">
                        <div v-for="allocation in payment.allocations" :key="allocation.id" class="grid gap-2 border-b border-[#E4E9E7] p-4 last:border-0 sm:grid-cols-[140px_1fr_auto] sm:items-center">
                            <p class="font-mono text-sm font-bold text-[#006B63]">{{ allocation.charge.charge_number }}</p>
                            <div><p class="font-semibold text-[#131B2E]">{{ allocation.charge.concept }}</p><p class="text-xs text-[#667085]">Aplicado {{ formatDate(allocation.allocated_at) }}</p></div>
                            <p class="font-mono font-bold text-[#131B2E]">{{ formatMoney(allocation.amount) }}</p>
                        </div>
                    </div>
                    <div v-else class="mt-4 border border-[#FEC84B] bg-[#FFFAEB] p-4 text-sm text-[#93370D]">Este pago todavía no se ha aplicado a ningún cargo.</div>
                </section>

                <section v-if="payment.refunds.length" class="border-t border-[#D8E0DE] bg-[#FFF9F8] p-6 sm:p-8">
                    <h2 class="flex items-center gap-2 text-lg font-bold text-[#912018]"><RotateCcw class="h-5 w-5" /> Reembolsos registrados</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="refund in payment.refunds" :key="refund.id" class="grid gap-2 border border-[#F5A3A0] bg-white p-4 sm:grid-cols-[1fr_auto]">
                            <div><p class="font-semibold text-[#912018]">{{ refund.reason }}</p><p class="mt-1 text-xs text-[#667085]">{{ formatDate(refund.refunded_at) }} · {{ refund.created_by || 'Usuario de la clínica' }}</p></div>
                            <p class="font-mono font-bold text-[#B42318]">− {{ formatMoney(refund.amount) }}</p>
                        </div>
                    </div>
                </section>

                <footer class="grid gap-6 border-t-2 border-[#131B2E] bg-[#F8FAFC] p-6 sm:p-8 md:grid-cols-[1fr_340px]">
                    <div class="text-xs leading-relaxed text-[#667085]">
                        <p class="font-bold uppercase tracking-[0.14em] text-[#455653]">Control del comprobante</p>
                        <p class="mt-2">Los métodos, asignaciones y reembolsos mostrados provienen del registro financiero de la clínica. Conserve el número {{ payment.payment_number }} para cualquier aclaración.</p>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-[#52615E]">Pago recibido</dt><dd class="font-mono font-semibold">{{ formatMoney(payment.total_amount) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-[#52615E]">Aplicado a cargos</dt><dd class="font-mono font-semibold">{{ formatMoney(payment.allocated_amount) }}</dd></div>
                        <div v-if="payment.unallocated_amount > 0" class="flex justify-between"><dt class="text-[#52615E]">Sin asignar</dt><dd class="font-mono font-semibold text-[#93370D]">{{ formatMoney(payment.unallocated_amount) }}</dd></div>
                        <div v-if="payment.refunded_amount > 0" class="flex justify-between"><dt class="text-[#52615E]">Reembolsado</dt><dd class="font-mono font-semibold text-[#B42318]">− {{ formatMoney(payment.refunded_amount) }}</dd></div>
                        <div class="flex justify-between border-t border-[#9AAEAA] pt-3 text-lg"><dt class="font-bold text-[#131B2E]">Neto recibido</dt><dd class="font-mono font-bold text-[#005C55]">{{ formatMoney(payment.net_amount) }}</dd></div>
                    </dl>
                </footer>
            </article>
        </div>
    </ClinicLayout>
</template>

<style>
@media print {
    @page { margin: 12mm; size: auto; }
    body { background: white !important; }
    body > #app > div > nav,
    body > #app > div > div > header,
    .no-print { display: none !important; }
    body > #app > div > div { margin-left: 0 !important; min-height: auto !important; }
    body > #app > div > div > main { padding: 0 !important; }
    .receipt-screen { max-width: none !important; }
    .receipt-paper { border: 0 !important; box-shadow: none !important; }
    .receipt-paper section,
    .receipt-paper footer { break-inside: avoid; }
    .receipt-header { padding-top: 0 !important; }
}
</style>
