<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ArrowLeft, CheckCircle2, XCircle } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface ProfessionalDetails {
    id: string
    full_name: string
}

interface ProcedureDetails {
    id: string
    name: string
    code: string | null
}

interface QuoteItemDetails {
    id: string
    tooth_number: number | null
    surface: string
    unit_price: number
    quantity: number
    discount_percentage: number
    subtotal: number
    total: number
    procedure: ProcedureDetails
}

interface QuoteDetails {
    id: string
    patient_id: string
    quote_number: string
    version: number
    alternative_name: string
    status: 'draft' | 'presented' | 'approved' | 'partially_approved' | 'rejected' | 'converted'
    subtotal: number
    discount_total: number
    tax_total: number
    grand_total: number
    notes: string | null
    created_at: string
    patient: PatientDetails
    professional?: ProfessionalDetails
    items: QuoteItemDetails[]
}

const props = defineProps<{
    quote: QuoteDetails
}>()

const approveForm = useForm({})
const rejectForm = useForm({})

function approveQuote() {
    if (confirm(`¿Aprobar el presupuesto ${props.quote.quote_number} y generar el Plan de Tratamiento correspondiente?`)) {
        approveForm.post(`/quotes/${props.quote.id}/approve`)
    }
}

function rejectQuote() {
    if (confirm('¿Marcar este presupuesto como rechazado?')) {
        rejectForm.post(`/quotes/${props.quote.id}/reject`)
    }
}
</script>

<template>
    <Head :title="`Presupuesto ${quote.quote_number} — ${quote.patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-white tracking-tight">Presupuesto Odontológico</h1>
                        <span class="px-3 py-1 bg-teal-500/10 text-teal-400 border border-teal-500/30 rounded-full font-mono text-xs font-bold">
                            {{ quote.quote_number }} (v{{ quote.version }})
                        </span>
                        <span
:class="[
                            quote.status === 'approved' || quote.status === 'converted' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                            quote.status === 'rejected' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                            'bg-slate-700 text-slate-300 border-slate-600'
                        ]" class="px-3 py-1 text-xs font-bold rounded-full border"
>
                            {{ quote.status.toUpperCase() }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 mt-1">
                        Paciente: <span class="text-white font-semibold">{{ quote.patient.full_name }}</span> ({{ quote.patient.record_number }}) | 
                        Alternativa: <span class="text-teal-400 font-bold">{{ quote.alternative_name }}</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a :href="`/patients/${quote.patient_id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                    </a>

                    <button
                        v-if="quote.status === 'draft' || quote.status === 'presented'"
                        class="flex items-center gap-1.5 px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 font-bold rounded-xl text-xs transition"
                        @click="rejectQuote"
                    >
                        <XCircle class="w-4 h-4" /> Rechazar
                    </button>

                    <button
                        v-if="quote.status === 'draft' || quote.status === 'presented'"
                        :disabled="approveForm.processing"
                        class="flex items-center gap-1.5 px-5 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-xl text-xs shadow-lg shadow-teal-500/20 transition"
                        @click="approveQuote"
                    >
                        <CheckCircle2 class="w-4 h-4" /> Aprobar & Generar Plan
                    </button>
                </div>
            </div>

            <!-- Items Table -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-900/80 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-700/60">
                        <tr>
                            <th class="px-4 py-3">Pieza</th>
                            <th class="px-4 py-3">Procedimiento</th>
                            <th class="px-4 py-3 text-right">P. Unit</th>
                            <th class="px-4 py-3 text-center">Cant.</th>
                            <th class="px-4 py-3 text-right">Desc.</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40 text-xs">
                        <tr v-for="it in quote.items" :key="it.id" class="hover:bg-slate-700/20 transition">
                            <td class="px-4 py-3 font-mono font-bold text-teal-400">
                                {{ it.tooth_number ? `Pz ${it.tooth_number}` : 'General' }}
                            </td>
                            <td class="px-4 py-3 text-white font-semibold">{{ it.procedure.name }}</td>
                            <td class="px-4 py-3 text-right font-mono">${{ it.unit_price.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-center">{{ it.quantity }}</td>
                            <td class="px-4 py-3 text-right font-mono text-amber-400">{{ it.discount_percentage }}%</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-teal-400">${{ it.total.toFixed(2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Summary Total Block -->
                <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/50 flex items-center justify-between text-xs">
                    <div class="space-y-1">
                        <div>Subtotal: <span class="font-mono text-slate-300">${{ quote.subtotal.toFixed(2) }}</span></div>
                        <div>Descuentos: <span class="font-mono text-amber-400">-${{ quote.discount_total.toFixed(2) }}</span></div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-400 uppercase tracking-wider block">Total Presupuesto</span>
                        <span class="text-2xl font-black text-teal-400 font-mono">${{ quote.grand_total.toFixed(2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>