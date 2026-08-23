<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ArrowLeft, CheckCircle2, Check, ListChecks } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface ProcedureDetails {
    id: string
    name: string
}

interface PlanItemDetails {
    id: string
    phase: number
    tooth_number: number | null
    surface: string
    price: number
    status: 'pending' | 'scheduled' | 'in_progress' | 'completed'
    completed_at: string | null
    procedure: ProcedureDetails
}

interface PlanDetails {
    id: string
    patient_id: string
    title: string
    status: 'active' | 'completed' | 'cancelled'
    total_estimated: number
    total_performed: number
    progress_percentage: number
    patient: PatientDetails
    items: PlanItemDetails[]
}

defineProps<{
    plan: PlanDetails
}>()

function completeItem(item: PlanItemDetails) {
    if (confirm(`¿Marcar como realizado el procedimiento "${item.procedure.name}"?`)) {
        useForm({}).post(`/treatment-items/${item.id}/complete`)
    }
}
</script>

<template>
    <Head :title="`${plan.title} — ${plan.patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                            <ListChecks class="w-6 h-6 text-teal-400" /> Plan de Tratamiento Activo
                        </h1>
                        <span
:class="[
                            plan.status === 'completed' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                            'bg-teal-500/20 text-teal-300 border-teal-500/30'
                        ]" class="px-3 py-1 text-xs font-bold rounded-full border"
>
                            {{ plan.status === 'completed' ? 'COMPLETADO' : 'EN EJECUCIÓN' }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 mt-1">
                        Paciente: <span class="text-white font-semibold">{{ plan.patient.full_name }}</span> ({{ plan.patient.record_number }}) | 
                        {{ plan.title }}
                    </p>
                </div>

                <a :href="`/patients/${plan.patient_id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                    <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                </a>
            </div>

            <!-- Progress Metrics Card -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-400 uppercase tracking-wider">Avance del Plan Clínico</span>
                    <span class="font-mono font-bold text-teal-400 text-sm">{{ plan.progress_percentage }}% Completado</span>
                </div>

                <!-- Progress Bar -->
                <div class="w-full h-3 bg-slate-900 rounded-full overflow-hidden border border-slate-700/60">
                    <div class="h-full bg-teal-500 transition-all duration-500" :style="{ width: `${plan.progress_percentage}%` }"></div>
                </div>

                <div class="grid grid-cols-2 gap-4 pt-2 text-xs">
                    <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700/40">
                        <span class="text-slate-500 block">Total Presupuestado:</span>
                        <span class="font-mono text-sm font-bold text-white">${{ plan.total_estimated.toFixed(2) }}</span>
                    </div>
                    <div class="p-3 bg-slate-900/60 rounded-xl border border-slate-700/40">
                        <span class="text-slate-500 block">Total Ejecutado / Realizado:</span>
                        <span class="font-mono text-sm font-bold text-emerald-400">${{ plan.total_performed.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Checklist of Procedures -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Procedimientos a Realizar</h2>

                <div class="space-y-2">
                    <div v-for="it in plan.items" :key="it.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex items-center justify-between text-xs">
                        <div class="flex items-center gap-3">
                            <span v-if="it.tooth_number" class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-400 font-mono font-bold flex items-center justify-center border border-teal-500/20">
                                Pz {{ it.tooth_number }}
                            </span>
                            <div>
                                <div class="text-sm font-bold text-white">{{ it.procedure.name }}</div>
                                <div class="text-slate-500 font-mono">${{ it.price.toFixed(2) }}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span v-if="it.status === 'completed'" class="flex items-center gap-1 text-emerald-400 font-bold">
                                <CheckCircle2 class="w-4 h-4" /> Realizado ({{ it.completed_at?.substring(0, 10) }})
                            </span>
                            <button
                                v-else
                                class="flex items-center gap-1 px-3 py-1.5 bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/30 font-bold rounded-lg transition"
                                @click="completeItem(it)"
                            >
                                <Check class="w-3.5 h-3.5" /> Marcar Realizado
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>