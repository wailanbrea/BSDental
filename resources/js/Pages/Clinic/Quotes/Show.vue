<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, CalendarDays, CheckCircle2, ClipboardList, FileText, ShieldCheck, Stethoscope, UserRound, XCircle } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface MedicalHistory { allergies: string[] | null; chronic_conditions: string[] | null }
interface PatientDetails { id: string; record_number: string; full_name: string; medical_history?: MedicalHistory | null }
interface ProfessionalDetails { id: string; full_name: string }
interface ProcedureDetails { id: string; name: string; code: string | null }
interface QuoteItemDetails {
    id: string; tooth_number: number | null; surface: string; unit_price: number; quantity: number
    discount_percentage: number; subtotal: number; tax: number; total: number; procedure: ProcedureDetails
}
interface TreatmentPlanSummary { id: string; status: string; progress_percentage: number }
interface QuoteDetails {
    id: string; patient_id: string; quote_number: string; version: number; alternative_name: string
    status: 'draft' | 'presented' | 'approved' | 'partially_approved' | 'rejected' | 'converted'
    subtotal: number; discount_total: number; tax_total: number; grand_total: number; notes: string | null
    expires_at: string | null; approved_at: string | null; approved_by_name: string | null; created_at: string
    patient: PatientDetails; professional?: ProfessionalDetails | null; items: QuoteItemDetails[]
    treatment_plan?: TreatmentPlanSummary | null
}

const props = defineProps<{ quote: QuoteDetails }>()
const approveForm = useForm({ approved_by_name: props.quote.patient.full_name })
const rejectForm = useForm({})
const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0)
const date = (value: string | null) => value ? new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(new Date(value)) : 'No definido'
const statusLabel = (status: QuoteDetails['status']) => ({ draft: 'Borrador', presented: 'Presentado', approved: 'Aprobado', partially_approved: 'Aprobación parcial', rejected: 'Rechazado', converted: 'Convertido a plan' })[status]
const statusClass = (status: QuoteDetails['status']) => status === 'converted' || status === 'approved'
    ? 'bg-[#D8ECE9] text-[#006B63] border-[#9BCDC7]'
    : status === 'rejected' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-amber-50 text-amber-800 border-amber-200'

function approveQuote() {
    if (window.confirm(`¿Aprobar ${props.quote.quote_number} y generar el plan de tratamiento?`)) approveForm.post(`/quotes/${props.quote.id}/approve`)
}
function rejectQuote() {
    if (window.confirm(`¿Marcar ${props.quote.quote_number} como rechazado?`)) rejectForm.post(`/quotes/${props.quote.id}/reject`)
}
</script>

<template>
    <Head :title="`Presupuesto ${quote.quote_number} — ${quote.patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1400px] space-y-5 p-4 md:p-7">
            <section class="border border-[#D8E0DE] bg-white p-4 md:p-5">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#D8ECE9] text-lg font-bold text-[#006B63]">{{ quote.patient.full_name.charAt(0) }}</div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-[#131B2E]">{{ quote.patient.full_name }}</h2><span class="font-mono text-xs text-[#667085]">{{ quote.patient.record_number }}</span></div>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <span v-for="allergy in quote.patient.medical_history?.allergies || []" :key="allergy" class="bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">Alergia: {{ allergy }}</span>
                                <span v-for="condition in quote.patient.medical_history?.chronic_conditions || []" :key="condition" class="bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">{{ condition }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="`/patients/${quote.patient_id}`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><UserRound class="h-4 w-4" /> Ficha 360</Link>
                        <Link :href="`/patients/${quote.patient_id}/treatment-plans`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ClipboardList class="h-4 w-4" /> Tratamientos</Link>
                    </div>
                </div>
            </section>

            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 lg:flex-row lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-2"><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Presupuesto odontológico</p><span class="border px-2 py-1 text-xs font-semibold" :class="statusClass(quote.status)">{{ statusLabel(quote.status) }}</span></div>
                    <h1 class="mt-2 text-2xl font-bold text-[#131B2E] md:text-3xl">{{ quote.quote_number }} · {{ quote.alternative_name }}</h1>
                    <p class="mt-1 text-sm text-[#667085]">Versión {{ quote.version }} · Creado {{ date(quote.created_at) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="`/patients/${quote.patient_id}/quotes`" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ArrowLeft class="h-4 w-4" /> Presupuestos</Link>
                        <Link v-if="quote.treatment_plan" :href="`/treatment-plans/${quote.treatment_plan.id}`" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white"><ClipboardList class="h-4 w-4" /> Ver plan de tratamiento</Link>
                    <button v-if="quote.status === 'draft' || quote.status === 'presented'" type="button" class="inline-flex h-10 items-center gap-2 border border-red-300 bg-white px-4 text-sm font-semibold text-red-700" :disabled="rejectForm.processing" @click="rejectQuote"><XCircle class="h-4 w-4" /> Rechazar</button>
                    <button v-if="quote.status === 'draft' || quote.status === 'presented'" type="button" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="approveForm.processing" @click="approveQuote"><CheckCircle2 class="h-4 w-4" /> Aprobar y generar plan</button>
                </div>
            </header>

            <section class="grid gap-4 md:grid-cols-3">
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Profesional tratante</p><p class="mt-2 flex items-center gap-2 font-semibold text-[#131B2E]"><Stethoscope class="h-4 w-4 text-[#006B63]" /> {{ quote.professional?.full_name || 'Sin asignar' }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Vigencia</p><p class="mt-2 flex items-center gap-2 font-semibold text-[#131B2E]"><CalendarDays class="h-4 w-4 text-[#006B63]" /> {{ date(quote.expires_at) }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Aprobación</p><p class="mt-2 flex items-center gap-2 font-semibold text-[#131B2E]"><ShieldCheck class="h-4 w-4 text-[#006B63]" /> {{ quote.approved_by_name || (quote.approved_at ? 'Firmante no registrado' : 'Pendiente') }}</p><p v-if="quote.approved_at" class="mt-1 text-xs text-[#667085]">{{ date(quote.approved_at) }}</p></article>
            </section>

            <section class="overflow-hidden border border-[#BDC9C6] bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="border-b border-[#BDC9C6] bg-[#F2F6F5] text-xs font-bold uppercase tracking-[0.08em] text-[#455653]"><tr><th class="px-4 py-3">Procedimiento</th><th class="px-4 py-3">Pieza</th><th class="px-4 py-3 text-center">Cant.</th><th class="px-4 py-3 text-right">Precio unit.</th><th class="px-4 py-3 text-right">Desc.</th><th class="px-4 py-3 text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="item in quote.items" :key="item.id" class="hover:bg-[#F8FAFC]">
                                <td class="px-4 py-4"><p class="font-semibold text-[#131B2E]">{{ item.procedure.name }}</p><p class="mt-0.5 font-mono text-xs text-[#667085]">{{ item.procedure.code || 'Sin código' }} · {{ item.surface || 'Todas las superficies' }}</p></td>
                                <td class="px-4 py-4 font-mono font-bold text-[#006B63]">{{ item.tooth_number || 'General' }}</td><td class="px-4 py-4 text-center text-[#455653]">{{ item.quantity }}</td><td class="px-4 py-4 text-right font-mono text-[#455653]">{{ money(item.unit_price) }}</td>
                                <td class="px-4 py-4 text-right font-mono" :class="item.discount_percentage > 0 ? 'text-emerald-700' : 'text-[#667085]'">{{ item.discount_percentage }}%</td><td class="px-4 py-4 text-right font-mono font-bold text-[#131B2E]">{{ money(item.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="grid border-t border-[#BDC9C6] bg-[#F8FAFC] lg:grid-cols-[1fr_360px]">
                    <div class="border-b border-[#D8E0DE] p-5 lg:border-b-0 lg:border-r"><p class="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-[#455653]"><FileText class="h-4 w-4" /> Notas clínicas y administrativas</p><p class="mt-3 whitespace-pre-line text-sm leading-6 text-[#455653]">{{ quote.notes || 'No se registraron notas para este presupuesto.' }}</p></div>
                    <dl class="space-y-3 p-5 text-sm"><div class="flex justify-between gap-4"><dt class="text-[#667085]">Subtotal bruto</dt><dd class="font-mono text-[#131B2E]">{{ money(quote.subtotal) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#667085]">Descuentos</dt><dd class="font-mono text-emerald-700">− {{ money(quote.discount_total) }}</dd></div><div class="flex justify-between gap-4"><dt class="text-[#667085]">Impuestos</dt><dd class="font-mono text-[#131B2E]">{{ money(quote.tax_total) }}</dd></div><div class="flex items-end justify-between gap-4 border-t border-[#BDC9C6] pt-4"><dt class="font-bold text-[#131B2E]">Total estimado</dt><dd class="font-mono text-2xl font-bold text-[#005C55]">{{ money(quote.grand_total) }}</dd></div></dl>
                </div>
            </section>
        </div>
    </ClinicLayout>
</template>
