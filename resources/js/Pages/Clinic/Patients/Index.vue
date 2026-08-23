<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { ArrowLeft, ArrowRight, ChevronRight, Plus, ReceiptText, Search, UserRound } from 'lucide-vue-next'
import { ref, watch } from 'vue'

interface PatientItem {
    id: string
    record_number: string
    full_name: string
    phone: string | null
    email: string | null
    status: string
    tags: string[] | null
    last_visit_at: string | null
    next_appointment_at: string | null
    balance_due: number | string | null
}

interface Paginated<T> {
    data: T[]
    current_page: number
    last_page: number
    from: number | null
    to: number | null
    total: number
    prev_page_url: string | null
    next_page_url: string | null
}

const props = defineProps<{
    patients: Paginated<PatientItem>
    branches: Array<{ id: string; name: string }>
    filters: { search: string | null; status: string | null; branch_id: string | null; tag: string | null; last_visit: string | null }
}>()

const search = ref(props.filters.search || '')
const status = ref(props.filters.status || '')
const branchId = ref(props.filters.branch_id || '')
const tag = ref(props.filters.tag || '')
const lastVisit = ref(props.filters.last_visit || '')
let searchTimer: ReturnType<typeof setTimeout> | null = null

function applyFilters() {
    router.get('/patients', {
        search: search.value || undefined,
        status: status.value || undefined,
        branch_id: branchId.value || undefined,
        tag: tag.value || undefined,
        last_visit: lastVisit.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 350)
})
watch([status, branchId, tag, lastVisit], applyFilters)

function initials(name: string) {
    return name.split(/\s+/).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
}

function formatDate(value: string | null) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('es-DO', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value))
}

function formatMoney(value: number | string | null) {
    return new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(Number(value || 0))
}
</script>

<template>
    <Head title="Pacientes — BSDental" />

    <ClinicLayout>
        <div class="space-y-5">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#64748B]">Gestión clínica</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#131B2E]">Pacientes</h1>
                    <p class="mt-1 text-sm text-[#64748B]">Directorio central de historias clínicas y estado financiero.</p>
                </div>
                <div class="flex flex-wrap gap-2"><Link href="/quotes" class="inline-flex h-10 items-center justify-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ReceiptText class="h-4 w-4" /> Centro de presupuestos</Link><Link href="/patients/create" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#004b46]"><Plus class="h-4 w-4" /> Nuevo paciente</Link></div>
            </header>

            <section class="rounded-lg border border-[#BDC9C6] bg-white p-3 shadow-[0_1px_2px_rgba(15,23,42,0.04)]">
                <div class="grid gap-2 lg:grid-cols-[minmax(280px,1fr)_repeat(4,auto)]">
                    <label class="flex h-11 items-center gap-3 border border-[#BDC9C6] bg-[#FAF8FF] px-3 focus-within:border-[#005C55] focus-within:ring-1 focus-within:ring-[#005C55]">
                        <Search class="h-4 w-4 shrink-0 text-[#455653]" />
                        <input v-model="search" type="search" placeholder="Buscar paciente, documento, teléfono o código HC…" class="w-full border-0 bg-transparent text-sm text-[#131B2E] outline-none placeholder:text-[#879592]" />
                    </label>
                    <select v-model="status" aria-label="Estado" class="h-11 border border-[#BDC9C6] bg-white px-3 text-sm text-[#455653] outline-none focus:border-[#005C55]">
                        <option value="">Estado</option><option value="active">Activos</option><option value="inactive">Inactivos</option><option value="archived">Archivados</option>
                    </select>
                    <select v-model="lastVisit" aria-label="Última visita" class="h-11 border border-[#BDC9C6] bg-white px-3 text-sm text-[#455653] outline-none focus:border-[#005C55]">
                        <option value="">Última visita</option><option value="30">Últimos 30 días</option><option value="90">Últimos 90 días</option><option value="365">Último año</option>
                    </select>
                    <select v-model="tag" aria-label="Etiqueta" class="h-11 border border-[#BDC9C6] bg-white px-3 text-sm text-[#455653] outline-none focus:border-[#005C55]">
                        <option value="">Etiqueta</option><option value="VIP">VIP</option><option value="seguimiento">Seguimiento</option><option value="ortodoncia">Ortodoncia</option><option value="pediatría">Pediatría</option>
                    </select>
                    <select v-model="branchId" aria-label="Sucursal" class="h-11 border border-[#BDC9C6] bg-white px-3 text-sm text-[#455653] outline-none focus:border-[#005C55]">
                        <option value="">Sucursal</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                    </select>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-[#BDC9C6] bg-white shadow-[0_1px_3px_rgba(15,23,42,0.05)]">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] border-collapse text-left">
                        <thead class="border-b border-[#BDC9C6] bg-[#F4F3FB] text-[11px] font-bold uppercase tracking-[0.12em] text-[#455653]">
                            <tr>
                                <th class="px-5 py-3">Paciente</th><th class="px-4 py-3">Código</th><th class="px-4 py-3">Teléfono</th><th class="px-4 py-3">Última visita</th><th class="px-4 py-3">Próxima cita</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D8E0DE] text-sm text-[#263633]">
                            <tr v-for="patient in patients.data" :key="patient.id" class="h-14 transition hover:bg-[#F7FAF9]">
                                <td class="px-5 py-2"><div class="flex items-center gap-3"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#B7C8C4] bg-[#E7F0FF] text-xs font-bold text-[#455A73]">{{ initials(patient.full_name) }}</span><div class="min-w-0"><p class="truncate font-semibold text-[#131B2E]">{{ patient.full_name }}</p><p class="truncate text-xs text-[#7A8986]">{{ patient.email || 'Sin correo registrado' }}</p></div></div></td>
                                <td class="px-4 py-2 font-mono text-xs font-semibold text-[#455653]">{{ patient.record_number }}</td>
                                <td class="px-4 py-2 data-tabular">{{ patient.phone || '—' }}</td>
                                <td class="px-4 py-2 data-tabular text-[#52615E]">{{ formatDate(patient.last_visit_at) }}</td>
                                <td class="px-4 py-2 data-tabular font-medium text-[#006B63]">{{ formatDate(patient.next_appointment_at) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold" :class="Number(patient.balance_due || 0) > 0 ? 'text-[#B42318]' : 'text-[#263633]'">{{ formatMoney(patient.balance_due) }}</td>
                                <td class="px-4 py-2"><span class="inline-flex rounded-sm px-2 py-1 text-xs font-semibold" :class="patient.status === 'active' ? 'bg-[#D8ECE9] text-[#006B63]' : 'bg-[#E8ECF7] text-[#455A73]'">{{ patient.status === 'active' ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-4 py-2 text-right"><Link :href="`/patients/${patient.id}`" class="inline-flex items-center gap-1 text-xs font-bold text-[#006B63] hover:underline">Ficha 360 <ChevronRight class="h-3.5 w-3.5" /></Link></td>
                            </tr>
                            <tr v-if="patients.data.length === 0"><td colspan="8" class="px-6 py-16 text-center"><UserRound class="mx-auto h-9 w-9 text-[#9AABA7]" /><p class="mt-3 font-semibold text-[#263633]">No encontramos pacientes</p><p class="mt-1 text-sm text-[#7A8986]">Prueba con otros criterios de búsqueda.</p></td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="flex flex-col gap-3 border-t border-[#BDC9C6] bg-[#FAF8FF] px-4 py-3 text-sm text-[#455653] sm:flex-row sm:items-center sm:justify-between">
                    <p>Mostrando {{ patients.from || 0 }} a {{ patients.to || 0 }} de {{ patients.total }} pacientes</p>
                    <div class="flex items-center gap-1">
                        <Link v-if="patients.prev_page_url" :href="patients.prev_page_url" preserve-scroll class="grid h-8 w-8 place-items-center border border-[#BDC9C6] bg-white hover:bg-[#EDF5F3]" aria-label="Página anterior"><ArrowLeft class="h-4 w-4" /></Link>
                        <span class="grid h-8 min-w-8 place-items-center bg-[#007D73] px-2 font-semibold text-white">{{ patients.current_page }}</span><span class="px-2 text-[#7A8986]">de {{ patients.last_page }}</span>
                        <Link v-if="patients.next_page_url" :href="patients.next_page_url" preserve-scroll class="grid h-8 w-8 place-items-center border border-[#BDC9C6] bg-white hover:bg-[#EDF5F3]" aria-label="Página siguiente"><ArrowRight class="h-4 w-4" /></Link>
                    </div>
                </footer>
            </section>
        </div>
    </ClinicLayout>
</template>
