<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { Activity, CalendarDays, ChevronLeft, ChevronRight, ClipboardPlus, FileCheck2, Search, Stethoscope } from 'lucide-vue-next'
import { reactive } from 'vue'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface Patient {
    id: string
    record_number: string
    full_name: string
}

interface Professional {
    id: string
    full_name: string
}

interface Encounter {
    id: string
    patient_id: string
    encounter_date: string
    chief_complaint: string | null
    status: string
    patient: Patient
    professional: Professional | null
    evolution: { assessment: string | null; treatment_performed: string | null } | null
}

interface Paginated<T> {
    data: T[]
    current_page: number
    last_page: number
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    patient: Patient | null
    encounters: Paginated<Encounter>
    professionals: Professional[]
    filters: Record<string, string | null>
    summary: { total: number; draft: number; finalized: number; today: number }
}>()

const filters = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    professional_id: props.filters.professional_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
})

function applyFilters() {
    const path = props.patient ? `/patients/${props.patient.id}/encounters` : '/encounters'
    router.get(path, filters, { preserveState: true, replace: true })
}

function clearFilters() {
    Object.assign(filters, { search: '', status: '', professional_id: '', date_from: '', date_to: '' })
    applyFilters()
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function statusLabel(status: string) {
    return ({ draft: 'Borrador', finalized: 'Finalizado', amended: 'Enmendado' } as Record<string, string>)[status] || status
}
</script>

<template>
    <Head title="Clínica — Historias y evoluciones" />

    <ClinicLayout>
        <div class="mx-auto max-w-[1500px] space-y-5 p-4 md:p-7">
            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 lg:flex-row lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Clínica</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#131B2E]">Historias y evoluciones</h1>
                    <p class="mt-1 text-sm text-[#667085]">
                        {{ patient ? `${patient.full_name} · ${patient.record_number}` : 'Actividad clínica consolidada de todos los pacientes.' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link v-if="patient" :href="`/patients/${patient.id}`" class="inline-flex h-10 items-center border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]">Volver a ficha</Link>
                    <Link :href="patient ? `/patients/${patient.id}/encounters/create` : '/patients'" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white shadow-sm hover:bg-[#004B46]">
                        <ClipboardPlus class="h-4 w-4" /> {{ patient ? 'Nueva evolución' : 'Seleccionar paciente' }}
                    </Link>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article
v-for="item in [
                    { label: 'Total de evoluciones', value: summary.total, icon: Stethoscope },
                    { label: 'Atenciones de hoy', value: summary.today, icon: CalendarDays },
                    { label: 'Borradores', value: summary.draft, icon: Activity },
                    { label: 'Registros sellados', value: summary.finalized, icon: FileCheck2 },
                ]" :key="item.label" class="border border-[#BDC9C6] bg-white p-4"
>
                    <div class="flex items-center justify-between"><p class="text-xs font-semibold text-[#667085]">{{ item.label }}</p><component :is="item.icon" class="h-4 w-4 text-[#006B63]" /></div>
                    <p class="mt-3 font-mono text-2xl font-bold text-[#131B2E]">{{ item.value }}</p>
                </article>
            </section>

            <form class="grid gap-3 border border-[#BDC9C6] bg-[#FAFBFB] p-4 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto]" @submit.prevent="applyFilters">
                <label class="relative"><Search class="absolute left-3 top-3 h-4 w-4 text-[#667085]" /><input v-model="filters.search" class="h-10 w-full border border-[#9AAEAA] bg-white pl-9 pr-3 text-sm" placeholder="Paciente, historia o motivo" /></label>
                <select v-model="filters.status" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm"><option value="">Todos los estados</option><option value="draft">Borrador</option><option value="finalized">Finalizado</option><option value="amended">Enmendado</option></select>
                <select v-model="filters.professional_id" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm"><option value="">Todos los profesionales</option><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select>
                <input v-model="filters.date_from" type="date" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm" aria-label="Desde" />
                <input v-model="filters.date_to" type="date" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm" aria-label="Hasta" />
                <div class="flex gap-2"><button type="submit" class="h-10 bg-[#005C55] px-4 text-sm font-semibold text-white">Filtrar</button><button type="button" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm" @click="clearFilters">Limpiar</button></div>
            </form>

            <section class="overflow-hidden border border-[#BDC9C6] bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-left text-sm">
                        <thead class="bg-[#EDF5F3] text-xs uppercase tracking-wide text-[#455653]"><tr><th class="px-4 py-3">Fecha</th><th class="px-4 py-3">Paciente</th><th class="px-4 py-3">Motivo / evaluación</th><th class="px-4 py-3">Profesional</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acción</th></tr></thead>
                        <tbody class="divide-y divide-[#D8E0DE]">
                            <tr v-for="encounter in encounters.data" :key="encounter.id" class="hover:bg-[#F7FAF9]">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-[#455653]">{{ formatDate(encounter.encounter_date) }}</td>
                                <td class="px-4 py-3"><Link :href="`/patients/${encounter.patient_id}`" class="font-semibold text-[#131B2E] hover:text-[#006B63]">{{ encounter.patient.full_name }}</Link><p class="font-mono text-xs text-[#667085]">{{ encounter.patient.record_number }}</p></td>
                                <td class="max-w-md px-4 py-3"><p class="truncate font-medium text-[#131B2E]">{{ encounter.chief_complaint || 'Atención odontológica' }}</p><p class="mt-1 truncate text-xs text-[#667085]">{{ encounter.evolution?.assessment || encounter.evolution?.treatment_performed || 'Sin evaluación registrada' }}</p></td>
                                <td class="px-4 py-3 text-[#455653]">{{ encounter.professional?.full_name || 'Sin asignar' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex px-2 py-1 text-xs font-semibold" :class="encounter.status === 'draft' ? 'bg-amber-50 text-amber-800' : 'bg-[#D8ECE9] text-[#006B63]'">{{ statusLabel(encounter.status) }}</span></td>
                                <td class="px-4 py-3 text-right"><Link :href="`/encounters/${encounter.id}`" class="font-semibold text-[#006B63] hover:underline">Abrir registro</Link></td>
                            </tr>
                            <tr v-if="!encounters.data.length"><td colspan="6" class="px-4 py-12 text-center text-[#667085]">No hay evoluciones para los filtros seleccionados.</td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex items-center justify-between border-t border-[#D8E0DE] px-4 py-3 text-sm text-[#667085]"><span>{{ encounters.total }} registros · Página {{ encounters.current_page }} de {{ encounters.last_page }}</span><div class="flex gap-2"><Link v-if="encounters.prev_page_url" :href="encounters.prev_page_url" class="grid h-8 w-8 place-items-center border border-[#BDC9C6]" aria-label="Página anterior"><ChevronLeft class="h-4 w-4" /></Link><Link v-if="encounters.next_page_url" :href="encounters.next_page_url" class="grid h-8 w-8 place-items-center border border-[#BDC9C6]" aria-label="Página siguiente"><ChevronRight class="h-4 w-4" /></Link></div></footer>
            </section>
        </div>
    </ClinicLayout>
</template>
