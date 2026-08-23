<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { ArrowLeft, CheckCircle2, CircleDollarSign, Clock3, ExternalLink, FileText, ReceiptText, UserRound } from 'lucide-vue-next'

interface ChargeAllocation {
    id: string
    amount: number
    allocated_at: string
    payment: {
        id: string
        payment_number: string
        total_amount: number
        refunded_amount: number
        status: string
        paid_at: string
        methods: string[]
    }
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
    status: string
    due_date: string | null
    created_at: string
    created_by: string | null
    professional: string | null
    procedure: string | null
    patient: { id: string; record_number: string; full_name: string }
    allocations: ChargeAllocation[]
}

defineProps<{ charge: ChargeDetails }>()

const paymentMethods: Record<string, string> = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    credit_card: 'Tarjeta de crédito',
    debit_card: 'Tarjeta de débito',
    transfer: 'Transferencia',
    zelle: 'Zelle',
    insurance: 'Seguro',
}

function formatMoney(value: number) {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value)
}

function formatDate(value: string | null, includeTime = true) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('es-DO', includeTime
        ? { dateStyle: 'medium', timeStyle: 'short' }
        : { dateStyle: 'medium' }).format(new Date(value))
}

function statusLabel(status: string) {
    return ({ pending: 'Pendiente', partially_paid: 'Pago parcial', paid: 'Pagado', cancelled: 'Cancelado' } as Record<string, string>)[status] || status
}
</script>

<template>
    <Head :title="`Cargo ${charge.charge_number}`" />

    <ClinicLayout>
        <div class="mx-auto max-w-6xl space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link :href="`/patients/${charge.patient.id}/billing`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55] transition hover:border-[#007D73]"><ArrowLeft class="h-4 w-4" /> Estado de cuenta</Link>
                <Link :href="`/patients/${charge.patient.id}`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#344054] transition hover:border-[#007D73]"><UserRound class="h-4 w-4" /> Ficha 360</Link>
            </div>

            <section class="border border-[#BDC9C6] bg-white">
                <header class="flex flex-col gap-5 border-b-2 border-[#007D73] bg-[#FAF8FF] p-6 lg:flex-row lg:items-start lg:justify-between lg:p-8">
                    <div class="flex items-start gap-4">
                        <div class="grid h-12 w-12 shrink-0 place-items-center bg-[#005C55] text-white"><FileText class="h-6 w-6" /></div>
                        <div><p class="font-mono text-sm font-bold text-[#006B63]">{{ charge.charge_number }}</p><h1 class="mt-1 text-2xl font-bold text-[#131B2E]">{{ charge.concept }}</h1><p v-if="charge.procedure && charge.procedure !== charge.concept" class="mt-2 text-sm text-[#52615E]">Procedimiento: {{ charge.procedure }}</p></div>
                    </div>
                    <div class="border px-4 py-3" :class="charge.status === 'paid' ? 'border-[#B7D9D4] bg-[#F1FAF8] text-[#006B63]' : 'border-[#FEC84B] bg-[#FFFAEB] text-[#93370D]'">
                        <p class="flex items-center gap-2 text-sm font-bold"><CheckCircle2 v-if="charge.status === 'paid'" class="h-4 w-4" /><Clock3 v-else class="h-4 w-4" /> {{ statusLabel(charge.status) }}</p>
                        <p class="mt-1 text-xs">Creado {{ formatDate(charge.created_at) }}</p>
                    </div>
                </header>

                <div class="grid border-b border-[#D8E0DE] md:grid-cols-2">
                    <div class="p-6 md:border-r md:border-[#D8E0DE] lg:p-8">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#667085]">Paciente</p>
                        <Link :href="`/patients/${charge.patient.id}`" class="mt-3 block text-lg font-bold text-[#131B2E] hover:text-[#006B63]">{{ charge.patient.full_name }}</Link>
                        <p class="mt-1 font-mono text-sm text-[#006B63]">{{ charge.patient.record_number }}</p>
                    </div>
                    <dl class="grid grid-cols-[150px_1fr] gap-y-3 p-6 text-sm lg:p-8">
                        <dt class="text-[#667085]">Profesional</dt><dd class="font-semibold text-[#131B2E]">{{ charge.professional || 'No asignado' }}</dd>
                        <dt class="text-[#667085]">Registrado por</dt><dd class="font-semibold text-[#131B2E]">{{ charge.created_by || 'Usuario de la clínica' }}</dd>
                        <dt class="text-[#667085]">Vencimiento</dt><dd class="font-semibold text-[#131B2E]">{{ formatDate(charge.due_date, false) }}</dd>
                    </dl>
                </div>

                <div class="grid gap-4 bg-[#F8FAFC] p-6 sm:grid-cols-2 lg:grid-cols-5 lg:p-8">
                    <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase text-[#667085]">Base</p><p class="mt-2 font-mono text-lg font-bold">{{ formatMoney(charge.amount) }}</p></div>
                    <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase text-[#667085]">Impuestos</p><p class="mt-2 font-mono text-lg font-bold">{{ formatMoney(charge.tax_amount) }}</p></div>
                    <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase text-[#667085]">Total</p><p class="mt-2 font-mono text-lg font-bold">{{ formatMoney(charge.total_amount) }}</p></div>
                    <div class="border border-[#B7D9D4] bg-[#F1FAF8] p-4"><p class="text-xs font-bold uppercase text-[#006B63]">Pagado</p><p class="mt-2 font-mono text-lg font-bold text-[#006B63]">{{ formatMoney(charge.paid_amount) }}</p></div>
                    <div class="border p-4" :class="charge.balance_due > 0 ? 'border-[#FEC84B] bg-[#FFFAEB]' : 'border-[#B7D9D4] bg-[#F1FAF8]'"><p class="text-xs font-bold uppercase" :class="charge.balance_due > 0 ? 'text-[#93370D]' : 'text-[#006B63]'">Saldo</p><p class="mt-2 font-mono text-lg font-bold" :class="charge.balance_due > 0 ? 'text-[#93370D]' : 'text-[#006B63]'">{{ formatMoney(charge.balance_due) }}</p></div>
                </div>
            </section>

            <section class="border border-[#BDC9C6] bg-white p-6 lg:p-8">
                <div class="flex items-center justify-between gap-3"><div><h2 class="flex items-center gap-2 text-xl font-bold text-[#131B2E]"><CircleDollarSign class="h-5 w-5 text-[#007D73]" /> Pagos aplicados</h2><p class="mt-1 text-sm text-[#667085]">Trazabilidad de cada asignación conciliada con este cargo.</p></div><span class="bg-[#D8ECE9] px-3 py-1 text-xs font-bold text-[#006B63]">{{ charge.allocations.length }} {{ charge.allocations.length === 1 ? 'asignación' : 'asignaciones' }}</span></div>

                <div v-if="charge.allocations.length" class="mt-5 overflow-hidden border border-[#D8E0DE]">
                    <Link v-for="allocation in charge.allocations" :key="allocation.id" :href="`/payments/${allocation.payment.id}`" class="grid gap-3 border-b border-[#E4E9E7] p-4 transition last:border-0 hover:bg-[#F1FAF8] md:grid-cols-[150px_1fr_160px_140px_auto] md:items-center">
                        <div><p class="font-mono font-bold text-[#006B63]">{{ allocation.payment.payment_number }}</p><p class="mt-1 text-xs text-[#667085]">{{ formatDate(allocation.payment.paid_at) }}</p></div>
                        <div><p class="font-semibold text-[#131B2E]">{{ allocation.payment.methods.map((method) => paymentMethods[method] || method).join(' + ') }}</p><p v-if="allocation.payment.refunded_amount > 0" class="mt-1 text-xs font-semibold text-[#B42318]">Reembolsado: {{ formatMoney(allocation.payment.refunded_amount) }}</p></div>
                        <div class="text-sm"><p class="text-xs text-[#667085]">Total del recibo</p><p class="font-mono font-semibold">{{ formatMoney(allocation.payment.total_amount) }}</p></div>
                        <div class="text-sm"><p class="text-xs text-[#667085]">Aplicado aquí</p><p class="font-mono font-bold text-[#006B63]">{{ formatMoney(allocation.amount) }}</p></div>
                        <ExternalLink class="h-4 w-4 text-[#007D73]" />
                    </Link>
                </div>
                <div v-else class="mt-5 border border-[#FEC84B] bg-[#FFFAEB] p-6 text-sm text-[#93370D]"><p class="font-bold">Sin pagos aplicados</p><p class="mt-1">Use el estado de cuenta para asignar un pago disponible a este cargo.</p><Link :href="`/patients/${charge.patient.id}/billing`" class="mt-3 inline-flex items-center gap-1 font-bold text-[#006B63]">Abrir facturación <ExternalLink class="h-3.5 w-3.5" /></Link></div>
            </section>

            <div class="border border-[#D8E0DE] bg-[#F8FAFC] p-4 text-xs leading-relaxed text-[#667085]"><p class="flex items-center gap-2 font-bold uppercase tracking-[0.12em] text-[#455653]"><ReceiptText class="h-4 w-4" /> Integridad financiera</p><p class="mt-2">Este detalle conserva el cargo original y muestra las asignaciones como movimientos separados. Los pagos confirmados no se editan destructivamente.</p></div>
        </div>
    </ClinicLayout>
</template>
