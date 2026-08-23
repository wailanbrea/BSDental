<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ChevronRight, CircleCheck, ListChecks } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface Patient { id: string; record_number: string; full_name: string }
interface PlanItem { id: string; status: string; price: number; tooth_number: number | null; procedure: { name: string } }
interface Plan { id: string; title: string; status: string; total_estimated: number; total_performed: number; progress_percentage: number; items: PlanItem[] }

defineProps<{ patient: Patient; plans: Plan[] }>()
const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0)
const statusLabel = (status: string) => ({ pending: 'Pendiente', in_progress: 'En curso', completed: 'Completado', cancelled: 'Cancelado' } as Record<string, string>)[status] || status
</script>

<template>
    <Head :title="`Planes de tratamiento — ${patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1400px] space-y-5 p-4 md:p-7">
            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 md:flex-row md:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Tratamientos</p><h1 class="mt-1 text-2xl font-bold text-[#131B2E]">Planes de tratamiento</h1><p class="mt-1 text-sm text-[#667085]">{{ patient.full_name }} · {{ patient.record_number }}</p></div><Link :href="`/patients/${patient.id}`" class="inline-flex h-10 items-center border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]">Volver a ficha</Link></header>

            <section class="space-y-4">
                <Link v-for="plan in plans" :key="plan.id" :href="`/treatment-plans/${plan.id}`" class="group block border border-[#BDC9C6] bg-white p-5 hover:border-[#007D73] hover:shadow-sm">
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start"><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-3"><h2 class="font-semibold text-[#131B2E]">{{ plan.title }}</h2><span class="bg-[#D8ECE9] px-2 py-1 text-xs font-semibold text-[#006B63]">{{ statusLabel(plan.status) }}</span></div><p class="mt-2 text-sm text-[#667085]">{{ plan.items.length }} procedimientos · {{ plan.items.filter(item => item.status === 'completed').length }} completados</p><div class="mt-4 h-2 overflow-hidden rounded-full bg-[#E2E8F0]"><div class="h-full rounded-full bg-[#007D73]" :style="{ width: `${Math.min(100, plan.progress_percentage || 0)}%` }"></div></div><p class="mt-1 text-right font-mono text-xs font-bold text-[#006B63]">{{ Math.round(plan.progress_percentage || 0) }}%</p></div><div class="flex items-end justify-between gap-8 lg:min-w-[300px]"><div><p class="text-xs text-[#667085]">Ejecutado</p><p class="font-mono font-semibold text-[#006B63]">{{ money(plan.total_performed) }}</p><p class="mt-2 text-xs text-[#667085]">Estimado</p><p class="font-mono text-lg font-bold text-[#131B2E]">{{ money(plan.total_estimated) }}</p></div><ChevronRight class="h-5 w-5 text-[#006B63] transition-transform group-hover:translate-x-1" /></div></div>
                </Link>
                <div v-if="!plans.length" class="border border-dashed border-[#9AAEAA] bg-white p-12 text-center"><ListChecks class="mx-auto h-8 w-8 text-[#667085]" /><p class="mt-3 font-semibold text-[#131B2E]">No hay planes de tratamiento activos.</p><p class="mt-1 text-sm text-[#667085]">Los planes se generan automáticamente al aprobar un presupuesto.</p><Link :href="`/patients/${patient.id}/quotes`" class="mt-3 inline-flex items-center gap-1 text-sm font-bold text-[#006B63]"><CircleCheck class="h-4 w-4" /> Revisar presupuestos</Link></div>
            </section>
        </div>
    </ClinicLayout>
</template>
