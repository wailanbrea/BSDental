<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, CalendarCheck, Check, CheckCircle2, Circle, ClipboardList, FileText, Stethoscope } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface PatientDetails { id: string; record_number: string; full_name: string }
interface ProcedureDetails { id: string; name: string; code?: string | null }
interface AppointmentSummary { id: string; starts_at?: string; status?: string }
interface PlanItemDetails {
    id: string; phase: number; tooth_number: number | null; surface: string; price: number
    status: 'pending' | 'scheduled' | 'in_progress' | 'completed'; completed_at: string | null
    procedure: ProcedureDetails; appointment?: AppointmentSummary | null
}
interface QuoteSummary { id: string; quote_number: string; alternative_name: string; grand_total: number }
interface PlanDetails {
    id: string; patient_id: string; title: string; status: 'active' | 'completed' | 'cancelled'
    total_estimated: number; total_performed: number; progress_percentage: number
    patient: PatientDetails; quote?: QuoteSummary | null; items: PlanItemDetails[]
}

const props = defineProps<{ plan: PlanDetails }>()
const actionForm = useForm({})
const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0)
const completedCount = computed(() => props.plan.items.filter(item => item.status === 'completed').length)
const displayProgress = computed(() => props.plan.items.length ? Math.round((completedCount.value / props.plan.items.length) * 100) : 0)
const remaining = computed(() => Math.max(0, props.plan.total_estimated - props.plan.total_performed))
const statusLabel = (status: PlanDetails['status']) => ({ active: 'En ejecución', completed: 'Completado', cancelled: 'Cancelado' })[status]
const itemStatus = (status: PlanItemDetails['status']) => ({ pending: 'Pendiente', scheduled: 'Agendado', in_progress: 'En curso', completed: 'Realizado' })[status]
const date = (value: string | null) => value ? new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(new Date(value)) : ''

function completeItem(item: PlanItemDetails) {
    if (window.confirm(`¿Marcar “${item.procedure.name}” como realizado? Esta acción actualizará el avance clínico.`)) {
        actionForm.post(`/treatment-items/${item.id}/complete`, { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="`${plan.title} — ${plan.patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1400px] space-y-5 p-4 md:p-7">
            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 lg:flex-row lg:items-end">
                <div><div class="flex flex-wrap items-center gap-2"><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Plan de tratamiento</p><span class="border border-[#9BCDC7] bg-[#D8ECE9] px-2 py-1 text-xs font-semibold text-[#006B63]">{{ statusLabel(plan.status) }}</span></div><h1 class="mt-2 text-2xl font-bold text-[#131B2E]">{{ plan.title }}</h1><p class="mt-1 text-sm text-[#667085]">{{ plan.patient.full_name }} · {{ plan.patient.record_number }}</p></div>
                <div class="flex flex-wrap gap-2"><Link :href="`/patients/${plan.patient_id}/treatment-plans`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ArrowLeft class="h-4 w-4" /> Planes</Link><Link v-if="plan.quote" :href="`/quotes/${plan.quote.id}`" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white"><FileText class="h-4 w-4" /> {{ plan.quote.quote_number }}</Link></div>
            </header>

            <section class="border border-[#BDC9C6] bg-white p-5">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Avance clínico</p><p class="mt-1 font-mono text-3xl font-bold text-[#005C55]">{{ displayProgress }}%</p></div><p class="text-sm text-[#455653]">{{ completedCount }} de {{ plan.items.length }} procedimientos realizados</p></div>
                <div class="mt-4 h-3 overflow-hidden rounded-full bg-[#E2E8F0]"><div class="h-full rounded-full bg-[#007D73] transition-all" :style="{ width: `${displayProgress}%` }"></div></div>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Estimado</p><p class="mt-2 font-mono text-xl font-bold text-[#131B2E]">{{ money(plan.total_estimated) }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Ejecutado</p><p class="mt-2 font-mono text-xl font-bold text-[#006B63]">{{ money(plan.total_performed) }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Pendiente</p><p class="mt-2 font-mono text-xl font-bold text-[#131B2E]">{{ money(remaining) }}</p></article>
            </section>

            <section class="border border-[#BDC9C6] bg-white">
                <div class="border-b border-[#D8E0DE] p-5"><h2 class="flex items-center gap-2 font-semibold text-[#131B2E]"><ClipboardList class="h-5 w-5 text-[#006B63]" /> Secuencia de procedimientos</h2><p class="mt-1 text-sm text-[#667085]">Completa cada prestación cuando exista evidencia clínica de su realización.</p></div>
                <div class="divide-y divide-[#E2E8F0]">
                    <article v-for="item in plan.items" :key="item.id" class="flex flex-col justify-between gap-4 p-4 md:flex-row md:items-center md:p-5">
                        <div class="flex min-w-0 items-start gap-3">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="item.status === 'completed' ? 'bg-[#D8ECE9] text-[#006B63]' : 'bg-[#F2F6F5] text-[#667085]'">
                                <CheckCircle2 v-if="item.status === 'completed'" class="h-5 w-5" /><Circle v-else class="h-5 w-5" />
                            </div>
                            <div><div class="flex flex-wrap items-center gap-2"><p class="font-semibold text-[#131B2E]">{{ item.procedure.name }}</p><span class="bg-[#F2F6F5] px-2 py-1 text-xs font-semibold text-[#455653]">Fase {{ item.phase }}</span></div><p class="mt-1 font-mono text-xs text-[#667085]">{{ item.procedure.code || 'Sin código' }} · {{ item.tooth_number ? `Pieza ${item.tooth_number}` : 'General' }} · {{ item.surface }}</p><p v-if="item.completed_at" class="mt-1 text-xs font-semibold text-[#006B63]">Realizado {{ date(item.completed_at) }}</p><p v-else-if="item.appointment" class="mt-1 flex items-center gap-1 text-xs text-[#455653]"><CalendarCheck class="h-3.5 w-3.5" /> Cita vinculada</p></div>
                        </div>
                        <div class="flex items-center justify-between gap-5 md:justify-end"><div class="text-right"><p class="font-mono font-bold text-[#131B2E]">{{ money(item.price) }}</p><p class="text-xs text-[#667085]">{{ itemStatus(item.status) }}</p></div><button v-if="item.status !== 'completed'" type="button" class="inline-flex h-9 items-center gap-2 rounded-md bg-[#005C55] px-3 text-xs font-semibold text-white disabled:opacity-50" :disabled="actionForm.processing" @click="completeItem(item)"><Check class="h-4 w-4" /> Marcar realizado</button></div>
                    </article>
                </div>
            </section>

            <section v-if="plan.quote" class="flex flex-col justify-between gap-3 border border-[#D8E0DE] bg-[#F8FAFC] p-4 md:flex-row md:items-center"><div><p class="flex items-center gap-2 text-sm font-semibold text-[#131B2E]"><Stethoscope class="h-4 w-4 text-[#006B63]" /> Originado desde {{ plan.quote.quote_number }}</p><p class="mt-1 text-xs text-[#667085]">{{ plan.quote.alternative_name }} · {{ money(plan.quote.grand_total) }}</p></div><Link :href="`/quotes/${plan.quote.id}`" class="text-sm font-bold text-[#006B63]">Ver presupuesto de origen →</Link></section>
        </div>
    </ClinicLayout>
</template>
