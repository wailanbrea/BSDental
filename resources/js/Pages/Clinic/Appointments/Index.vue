<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { CalendarClock, Check, ChevronLeft, ChevronRight, Clock3, Filter, LockKeyhole, MapPin, Play, Plus, RotateCcw, UserCheck, X } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'

interface PatientSummary { id: string; record_number: string; first_name: string; last_name: string; phone: string | null }
interface ProfessionalSummary { id: string; full_name: string; color: string }
interface RoomSummary { id: string; name: string }
interface AppointmentTypeSummary { id: string; name: string; color: string; duration_minutes: number }
interface AppointmentItem {
    id: string; patient_id: string; professional_id: string; branch_id: string; room_id: string | null
    start_time: string; end_time: string; duration_minutes: number; status: string; reason: string | null
    patient: { full_name: string; record_number: string }; professional: ProfessionalSummary; room: RoomSummary | null; appointment_type: AppointmentTypeSummary | null
}
interface BranchItem { id: string; name: string; rooms: RoomSummary[] }
interface ScheduleBlockItem {
    id: string; title: string; reason: string; start_time: string; end_time: string
    professional: ProfessionalSummary | null; room: RoomSummary | null
}

const props = defineProps<{
    branches: BranchItem[]; professionals: ProfessionalSummary[]; appointmentTypes: AppointmentTypeSummary[]; patients: PatientSummary[]
    appointments: AppointmentItem[]; blocks: ScheduleBlockItem[]
    filters: { branch_id: string; professional_id: string | null; room_id: string | null; date: string; view: 'day' | 'week' | 'month'; patient_id: string | null; appointment_id: string | null; open_create: boolean }
}>()

const selectedDate = ref(props.filters.date)
const selectedBranch = ref(props.filters.branch_id)
const selectedProfessional = ref(props.filters.professional_id || '')
const selectedRoom = ref(props.filters.room_id || '')
const view = ref(props.filters.view)
const showFilters = ref(false)
const isCreatingModal = ref(props.filters.open_create)
const isBlockModal = ref(false)
const selectedAppointment = ref<AppointmentItem | null>(null)
const isRescheduling = ref(false)
const isCancelling = ref(false)
const dayStart = 8
const dayEnd = 19
const hourHeight = 56

const newAppointmentForm = useForm({
    patient_id: props.filters.patient_id || '', professional_id: props.professionals[0]?.id || '', branch_id: props.filters.branch_id || props.branches[0]?.id || '',
    room_id: '', appointment_type_id: props.appointmentTypes[0]?.id || '', start_time: `${props.filters.date}T09:00`, duration_minutes: 30, reason: '',
})
const blockForm = useForm({
    branch_id: props.filters.branch_id || props.branches[0]?.id || '', professional_id: '', room_id: '', title: 'Bloqueo clínico', reason: 'meeting',
    start_time: `${props.filters.date}T13:00`, end_time: `${props.filters.date}T14:00`,
})
const rescheduleForm = useForm({
    start_time: '', duration_minutes: 30, professional_id: '', room_id: '', reason: '',
})
const cancellationForm = useForm({ status: 'cancelled', cancellation_reason: '' })

onMounted(() => {
    if (props.filters.appointment_id) selectedAppointment.value = props.appointments.find((item) => item.id === props.filters.appointment_id) || null
    if (props.filters.open_create || props.filters.appointment_id) {
        const url = new URL(window.location.href)
        url.searchParams.delete('create')
        url.searchParams.delete('patient_id')
        url.searchParams.delete('appointment_id')
        window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`)
    }
})

function asLocalDate(value: string) { const [year, month, day] = value.slice(0, 10).split('-').map(Number); return new Date(year, month - 1, day) }
function isoDate(date: Date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}` }
function addDays(date: Date, amount: number) { const next = new Date(date); next.setDate(next.getDate() + amount); return next }
function startOfWeek(date: Date) { const day = date.getDay() || 7; return addDays(date, 1 - day) }

const visibleDays = computed(() => {
    const selected = asLocalDate(selectedDate.value)
    if (view.value === 'day') return [selected]
    if (view.value === 'week') return Array.from({ length: 7 }, (_, index) => addDays(startOfWeek(selected), index))
    const first = new Date(selected.getFullYear(), selected.getMonth(), 1)
    return Array.from({ length: 42 }, (_, index) => addDays(startOfWeek(first), index))
})
const hours = computed(() => Array.from({ length: dayEnd - dayStart + 1 }, (_, index) => dayStart + index))
const title = computed(() => new Intl.DateTimeFormat('es-DO', view.value === 'day' ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } : { month: 'long', year: 'numeric' }).format(asLocalDate(selectedDate.value)))
const availableRooms = computed(() => props.branches.find((branch) => branch.id === selectedBranch.value)?.rooms || [])
const appointmentRooms = computed(() => props.branches.find((branch) => branch.id === selectedAppointment.value?.branch_id)?.rooms || [])

function applyFilter() {
    router.get('/appointments', { branch_id: selectedBranch.value, professional_id: selectedProfessional.value || undefined, room_id: selectedRoom.value || undefined, date: selectedDate.value, view: view.value }, { preserveState: true, replace: true })
}
function changeBranch() { selectedRoom.value = ''; applyFilter() }
function changeView(next: 'day' | 'week' | 'month') { view.value = next; applyFilter() }
function navigate(direction: number) {
    const amount = view.value === 'month' ? 28 * direction : view.value === 'week' ? 7 * direction : direction
    selectedDate.value = isoDate(addDays(asLocalDate(selectedDate.value), amount)); applyFilter()
}
function goToday() { selectedDate.value = isoDate(new Date()); applyFilter() }
function appointmentsForDay(day: Date) { return props.appointments.filter((item) => item.start_time.slice(0, 10) === isoDate(day)) }
function blocksForDay(day: Date) { return props.blocks.filter((item) => item.start_time.slice(0, 10) === isoDate(day)) }
function eventStyle(item: AppointmentItem) {
    const [hour, minute] = item.start_time.slice(11, 16).split(':').map(Number)
    const top = ((hour + minute / 60) - dayStart) * hourHeight
    return { top: `${Math.max(0, top)}px`, height: `${Math.max(40, item.duration_minutes / 60 * hourHeight)}px` }
}
function blockStyle(item: ScheduleBlockItem) {
    const [startHour, startMinute] = item.start_time.slice(11, 16).split(':').map(Number)
    const [endHour, endMinute] = item.end_time.slice(11, 16).split(':').map(Number)
    const top = ((startHour + startMinute / 60) - dayStart) * hourHeight
    const duration = (endHour + endMinute / 60) - (startHour + startMinute / 60)
    return { top: `${Math.max(0, top)}px`, height: `${Math.max(38, duration * hourHeight)}px` }
}
function eventClass(status: string) {
    if (status === 'cancelled' || status === 'no_show') return 'border-[#D92D20] bg-[#FEE4E2] text-[#912018]'
    if (status === 'checked_in' || status === 'waiting') return 'border-[#2458C6] bg-[#DDE7FF] text-[#163F91]'
    if (status === 'in_progress') return 'border-[#007D73] bg-[#D8ECE9] text-[#005C55]'
    if (status === 'completed') return 'border-[#667085] bg-[#EAECF0] text-[#475467]'
    return 'border-[#0E7490] bg-[#DDF3F5] text-[#155E75]'
}
function updateStatus(item: AppointmentItem, status: string) {
    useForm({ status }).put(`/appointments/${item.id}/status`, { preserveScroll: true, onSuccess: () => { selectedAppointment.value = null } })
}
function openAppointment(item: AppointmentItem) { selectedAppointment.value = item; isRescheduling.value = false; isCancelling.value = false }
function openCancellation(item: AppointmentItem) {
    selectedAppointment.value = item
    cancellationForm.reset()
    isCancelling.value = true
    isRescheduling.value = false
}
function openReschedule(item: AppointmentItem) {
    selectedAppointment.value = item
    rescheduleForm.start_time = item.start_time.slice(0, 16)
    rescheduleForm.duration_minutes = item.duration_minutes
    rescheduleForm.professional_id = item.professional_id
    rescheduleForm.room_id = item.room_id || ''
    rescheduleForm.reason = item.reason || ''
    isRescheduling.value = true
    isCancelling.value = false
}
function submitReschedule() {
    if (!selectedAppointment.value) return
    rescheduleForm.post(`/appointments/${selectedAppointment.value.id}/reschedule`, { preserveScroll: true, onSuccess: () => { selectedAppointment.value = null; isRescheduling.value = false } })
}
function submitCancellation() {
    if (!selectedAppointment.value) return
    cancellationForm.put(`/appointments/${selectedAppointment.value.id}/status`, { preserveScroll: true, onSuccess: () => { selectedAppointment.value = null; isCancelling.value = false; cancellationForm.reset() } })
}
function openCreate(day: string | Date = selectedDate.value, hour = 9) { newAppointmentForm.start_time = `${typeof day === 'string' ? day : isoDate(day)}T${String(hour).padStart(2, '0')}:00`; isCreatingModal.value = true }
function submitAppointment() { newAppointmentForm.post('/appointments', { onSuccess: () => { isCreatingModal.value = false; newAppointmentForm.reset('patient_id', 'reason', 'room_id') } }) }
function submitBlock() { blockForm.post('/appointments/blocks', { onSuccess: () => { isBlockModal.value = false; blockForm.reset('title', 'room_id') } }) }
function formatTime(value: string) { const [hour, minute] = value.slice(11, 16).split(':').map(Number); return new Intl.DateTimeFormat('es-DO', { hour: '2-digit', minute: '2-digit' }).format(new Date(2000, 0, 1, hour, minute)) }
function formatDateTime(value: string) {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number)
    const [hour, minute] = value.slice(11, 16).split(':').map(Number)
    return new Intl.DateTimeFormat('es-DO', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(year, month - 1, day, hour, minute))
}
function statusLabel(status: string) {
    return ({ scheduled: 'Programada', confirmed: 'Confirmada', checked_in: 'Registrado', waiting: 'En espera', in_progress: 'En consulta', completed: 'Completada', cancelled: 'Cancelada', no_show: 'No asistió' } as Record<string, string>)[status] || status
}
</script>

<template>
    <Head title="Agenda — BSDental" />
    <ClinicLayout>
        <div class="space-y-5">
            <header class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div><p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#64748B]">Operación clínica</p><h1 class="mt-1 text-2xl font-bold capitalize text-[#131B2E]">Agenda</h1><p class="mt-1 text-sm text-[#64748B]">Coordina profesionales, sillones y flujo de pacientes.</p></div>
                <button class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white hover:bg-[#004b46]" @click="openCreate()"><Plus class="h-4 w-4" /> Nueva cita</button>
            </header>

            <section class="rounded-lg border border-[#BDC9C6] bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="flex flex-wrap items-center gap-2">
                        <button class="h-10 border border-[#BDC9C6] px-4 text-sm font-semibold text-[#263633] hover:bg-[#EDF5F3]" @click="goToday">Hoy</button>
                        <div class="flex"><button class="grid h-10 w-10 place-items-center border border-r-0 border-[#BDC9C6] hover:bg-[#EDF5F3]" @click="navigate(-1)"><ChevronLeft class="h-4 w-4" /></button><button class="grid h-10 w-10 place-items-center border border-[#BDC9C6] hover:bg-[#EDF5F3]" @click="navigate(1)"><ChevronRight class="h-4 w-4" /></button></div>
                        <h2 class="ml-2 text-xl font-bold capitalize text-[#131B2E]">{{ title }}</h2>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex overflow-hidden rounded-md border border-[#BDC9C6]"><button v-for="option in ['day', 'week', 'month'] as const" :key="option" class="h-10 min-w-20 border-r border-[#BDC9C6] px-3 text-sm last:border-0" :class="view === option ? 'bg-[#E5E9FF] font-semibold text-[#005C55]' : 'bg-white text-[#455653]'" @click="changeView(option)">{{ option === 'day' ? 'Día' : option === 'week' ? 'Semana' : 'Mes' }}</button></div>
                        <button class="inline-flex h-10 items-center gap-2 border border-[#BDC9C6] px-3 text-sm text-[#455653] hover:bg-[#EDF5F3]" @click="showFilters = !showFilters"><Filter class="h-4 w-4" /> Filtros</button>
                        <button class="inline-flex h-10 items-center gap-2 border border-[#BDC9C6] px-3 text-sm text-[#455653] hover:bg-[#EDF5F3]" @click="isBlockModal = true"><LockKeyhole class="h-4 w-4" /> Bloquear</button>
                    </div>
                </div>
                <div v-if="showFilters" class="mt-4 grid gap-3 border-t border-[#D8E0DE] pt-4 md:grid-cols-3">
                    <select v-model="selectedBranch" aria-label="Filtrar por sucursal" class="h-10 border border-[#BDC9C6] bg-white px-3 text-sm" @change="changeBranch"><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select>
                    <select v-model="selectedProfessional" class="h-10 border border-[#BDC9C6] bg-white px-3 text-sm" @change="applyFilter"><option value="">Todos los profesionales</option><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select>
                    <select v-model="selectedRoom" aria-label="Filtrar por sillón" class="h-10 border border-[#BDC9C6] bg-white px-3 text-sm" @change="applyFilter"><option value="">Todos los sillones</option><option v-for="room in availableRooms" :key="room.id" :value="room.id">{{ room.name }}</option></select>
                </div>
            </section>

            <section v-if="view !== 'month'" class="overflow-hidden rounded-lg border border-[#BDC9C6] bg-white shadow-sm">
                <div class="overflow-x-auto">
<div class="min-w-[900px]">
                    <div class="grid border-b border-[#BDC9C6] bg-[#F4F3FB]" :style="{ gridTemplateColumns: `88px repeat(${visibleDays.length}, minmax(120px, 1fr))` }">
                        <div class="grid place-items-center border-r border-[#BDC9C6]"><Clock3 class="h-4 w-4 text-[#455653]" /></div>
                        <div v-for="day in visibleDays" :key="isoDate(day)" class="border-r border-[#BDC9C6] px-3 py-2 text-center last:border-0" :class="isoDate(day) === selectedDate ? 'bg-[#E5E9FF]' : ''"><p class="text-[11px] font-bold uppercase tracking-wider text-[#455653]">{{ new Intl.DateTimeFormat('es-DO', { weekday: 'short' }).format(day) }}</p><p class="mt-1 text-lg font-semibold text-[#131B2E]">{{ day.getDate() }}</p></div>
                    </div>
                    <div class="grid" :style="{ gridTemplateColumns: `88px repeat(${visibleDays.length}, minmax(120px, 1fr))` }">
                        <div class="border-r border-[#BDC9C6] bg-[#FAF8FF]"><div v-for="hour in hours" :key="hour" class="border-b border-[#E1E7E5] pr-3 pt-1 text-right font-mono text-xs text-[#455653]" :style="{ height: `${hourHeight}px` }">{{ String(hour).padStart(2, '0') }}:00</div></div>
                        <div v-for="day in visibleDays" :key="isoDate(day)" class="relative border-r border-[#D8E0DE] last:border-0" :style="{ height: `${hours.length * hourHeight}px`, backgroundImage: `repeating-linear-gradient(to bottom, transparent 0, transparent ${hourHeight - 1}px, #E1E7E5 ${hourHeight}px)` }" @dblclick="openCreate(day)">
                            <article v-for="block in blocksForDay(day)" :key="block.id" class="absolute left-1 right-1 z-10 overflow-hidden border-l-4 border-[#B54708] bg-[repeating-linear-gradient(135deg,#FEF0C7,#FEF0C7_8px,#FFFAEB_8px,#FFFAEB_16px)] px-2 py-1 text-xs text-[#7A2E0E] shadow-sm" :style="blockStyle(block)">
                                <p class="flex items-center gap-1 truncate font-semibold"><LockKeyhole class="h-3.5 w-3.5 shrink-0" /> {{ block.title }}</p>
                                <p class="mt-0.5 truncate opacity-80">{{ formatTime(block.start_time) }}–{{ formatTime(block.end_time) }}<template v-if="block.room"> · {{ block.room.name }}</template></p>
                            </article>
                            <article v-for="item in appointmentsForDay(day)" :key="item.id" class="absolute left-1 right-1 z-20 cursor-pointer overflow-hidden border-l-4 px-2 py-1 text-xs shadow-sm hover:ring-2 hover:ring-[#005C55]/25" :class="eventClass(item.status)" :style="eventStyle(item)" @click.stop="openAppointment(item)">
                                <p class="truncate font-mono font-bold">{{ formatTime(item.start_time) }} · {{ item.appointment_type?.name || item.reason || 'Consulta' }}</p><p class="truncate text-sm font-semibold">{{ item.patient.full_name }}</p><p class="truncate opacity-75">{{ item.professional.full_name }}</p>
                                <div class="mt-1 flex gap-1"><button v-if="['scheduled','confirmed'].includes(item.status)" title="Check-in" @click.stop="updateStatus(item, 'checked_in')"><UserCheck class="h-3.5 w-3.5" /></button><button v-if="['checked_in','waiting'].includes(item.status)" title="Iniciar" @click.stop="updateStatus(item, 'in_progress')"><Play class="h-3.5 w-3.5" /></button><button v-if="item.status === 'in_progress'" title="Finalizar" @click.stop="updateStatus(item, 'completed')"><Check class="h-3.5 w-3.5" /></button><button v-if="!['completed','cancelled'].includes(item.status)" title="Cancelar" @click.stop="openCancellation(item)"><X class="h-3.5 w-3.5" /></button></div>
                            </article>
                        </div>
                    </div>
                </div>
</div>
            </section>

            <section v-else class="overflow-hidden rounded-lg border border-[#BDC9C6] bg-white shadow-sm">
                <div class="grid grid-cols-7 border-b border-[#BDC9C6] bg-[#F4F3FB]"><div v-for="label in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="label" class="border-r border-[#BDC9C6] px-3 py-2 text-center text-xs font-bold uppercase tracking-wider text-[#455653] last:border-0">{{ label }}</div></div>
                <div class="grid grid-cols-7"><button v-for="day in visibleDays" :key="isoDate(day)" class="min-h-28 border-b border-r border-[#D8E0DE] p-2 text-left hover:bg-[#F7FAF9]" :class="day.getMonth() !== asLocalDate(selectedDate).getMonth() ? 'bg-[#FAF8FF] text-[#98A2B3]' : ''" @click="openCreate(day)"><span class="text-sm font-semibold">{{ day.getDate() }}</span><span v-for="item in appointmentsForDay(day).slice(0, 3)" :key="item.id" class="mt-1 block truncate border-l-2 px-1.5 py-1 text-[11px]" :class="eventClass(item.status)" @click.stop="openAppointment(item)">{{ formatTime(item.start_time) }} {{ item.patient.full_name }}</span><span v-for="block in blocksForDay(day).slice(0, 1)" :key="block.id" class="mt-1 block truncate border-l-2 border-[#B54708] bg-[#FEF0C7] px-1.5 py-1 text-[11px] text-[#7A2E0E]">Bloqueo · {{ block.title }}</span></button></div>
            </section>
        </div>

        <div v-if="isCreatingModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/35 p-4 backdrop-blur-sm" @click.self="isCreatingModal = false">
<form class="w-full max-w-2xl rounded-lg border border-[#BDC9C6] bg-white shadow-2xl" @submit.prevent="submitAppointment">
<header class="flex items-center justify-between border-b border-[#D8E0DE] px-5 py-4"><div><h2 class="font-bold text-[#131B2E]">Nueva cita</h2><p class="text-xs text-[#64748B]">Reserva rápida de agenda clínica</p></div><button type="button" @click="isCreatingModal = false"><X class="h-5 w-5" /></button></header><div class="grid gap-4 p-5 md:grid-cols-2">
            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Paciente</span><select v-model="newAppointmentForm.patient_id" required class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="" disabled>Selecciona un paciente</option><option v-for="patient in patients" :key="patient.id" :value="patient.id">{{ patient.last_name }}, {{ patient.first_name }} · {{ patient.record_number }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Profesional</span><select v-model="newAppointmentForm.professional_id" required class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Sucursal</span><select v-model="newAppointmentForm.branch_id" required class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Fecha y hora</span><input v-model="newAppointmentForm.start_time" type="datetime-local" required class="h-10 w-full border border-[#BDC9C6] px-3 text-sm" /></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Tipo</span><select v-model="newAppointmentForm.appointment_type_id" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option v-for="type in appointmentTypes" :key="type.id" :value="type.id">{{ type.name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Sillón</span><select v-model="newAppointmentForm.room_id" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="">Sin asignar</option><option v-for="room in branches.find((branch) => branch.id === newAppointmentForm.branch_id)?.rooms || []" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Duración</span><select v-model="newAppointmentForm.duration_minutes" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option :value="30">30 min</option><option :value="45">45 min</option><option :value="60">60 min</option><option :value="90">90 min</option></select></label>
            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Motivo</span><input v-model="newAppointmentForm.reason" class="h-10 w-full border border-[#BDC9C6] px-3 text-sm" placeholder="Motivo de consulta o procedimiento" /></label>
        </div><footer class="flex justify-end gap-2 border-t border-[#D8E0DE] px-5 py-4"><button type="button" class="h-10 border border-[#BDC9C6] px-4 text-sm" @click="isCreatingModal = false">Cancelar</button><button :disabled="newAppointmentForm.processing" class="h-10 bg-[#005C55] px-5 text-sm font-semibold text-white disabled:opacity-60">Guardar cita</button></footer>
</form>
</div>

        <div v-if="isBlockModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/35 p-4" @click.self="isBlockModal = false">
<form class="w-full max-w-xl rounded-lg bg-white shadow-2xl" @submit.prevent="submitBlock">
<header class="flex items-center justify-between border-b border-[#D8E0DE] px-5 py-4"><div><h2 class="font-bold text-[#131B2E]">Bloquear horario</h2><p class="text-xs text-[#64748B]">Reserva un espacio para reunión, mantenimiento o indisponibilidad.</p></div><button type="button" @click="isBlockModal = false"><X class="h-5 w-5" /></button></header><div class="grid gap-4 p-5 md:grid-cols-2">
            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Título</span><input v-model="blockForm.title" required class="h-10 w-full border border-[#BDC9C6] px-3 text-sm" placeholder="Motivo del bloqueo" /></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Sucursal</span><select v-model="blockForm.branch_id" required class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Tipo</span><select v-model="blockForm.reason" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="meeting">Reunión</option><option value="unavailable">Indisponibilidad</option><option value="maintenance">Mantenimiento</option><option value="other">Otro</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Profesional</span><select v-model="blockForm.professional_id" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="">Todos</option><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Sillón</span><select v-model="blockForm.room_id" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="">Todos</option><option v-for="room in branches.find((branch) => branch.id === blockForm.branch_id)?.rooms || []" :key="room.id" :value="room.id">{{ room.name }}</option></select></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Inicio</span><input v-model="blockForm.start_time" type="datetime-local" required class="h-10 w-full border border-[#BDC9C6] px-3 text-sm" /></label>
            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Fin</span><input v-model="blockForm.end_time" type="datetime-local" required class="h-10 w-full border border-[#BDC9C6] px-3 text-sm" /></label>
        </div><footer class="flex justify-end gap-2 border-t border-[#D8E0DE] p-4"><button type="button" class="h-10 border border-[#BDC9C6] px-4" @click="isBlockModal = false">Cancelar</button><button :disabled="blockForm.processing" class="h-10 bg-[#005C55] px-4 font-semibold text-white disabled:opacity-60">Crear bloqueo</button></footer>
</form>
</div>

        <div v-if="selectedAppointment" class="fixed inset-0 z-50 flex justify-end bg-[#0F172A]/35 backdrop-blur-sm" @click.self="selectedAppointment = null">
<aside class="h-full w-full max-w-xl overflow-y-auto border-l border-[#BDC9C6] bg-white shadow-2xl">
            <header class="flex items-start justify-between border-b border-[#D8E0DE] px-6 py-5"><div><p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#64748B]">Detalle de cita</p><h2 class="mt-1 text-xl font-bold text-[#131B2E]">{{ selectedAppointment.patient.full_name }}</h2><p class="mt-1 font-mono text-xs text-[#64748B]">Historia clínica {{ selectedAppointment.patient.record_number }}</p></div><button type="button" class="grid h-9 w-9 place-items-center rounded-full hover:bg-[#EDF5F3]" @click="selectedAppointment = null"><X class="h-5 w-5" /></button></header>
            <div class="space-y-5 p-6">
                <div class="flex items-center justify-between rounded-lg border border-[#D8E0DE] bg-[#F7FAF9] p-4"><div><p class="text-xs text-[#64748B]">Estado operativo</p><p class="mt-1 font-semibold text-[#131B2E]">{{ statusLabel(selectedAppointment.status) }}</p></div><span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="eventClass(selectedAppointment.status)">{{ statusLabel(selectedAppointment.status) }}</span></div>
                <dl class="grid gap-4 rounded-lg border border-[#D8E0DE] p-4 sm:grid-cols-2"><div><dt class="text-xs text-[#64748B]">Fecha y hora</dt><dd class="mt-1 flex items-center gap-2 text-sm font-semibold text-[#263633]"><CalendarClock class="h-4 w-4 text-[#005C55]" /> {{ formatDateTime(selectedAppointment.start_time) }}</dd></div><div><dt class="text-xs text-[#64748B]">Duración</dt><dd class="mt-1 text-sm font-semibold text-[#263633]">{{ selectedAppointment.duration_minutes }} minutos</dd></div><div><dt class="text-xs text-[#64748B]">Profesional</dt><dd class="mt-1 text-sm font-semibold text-[#263633]">{{ selectedAppointment.professional.full_name }}</dd></div><div><dt class="text-xs text-[#64748B]">Sillón</dt><dd class="mt-1 flex items-center gap-2 text-sm font-semibold text-[#263633]"><MapPin class="h-4 w-4 text-[#005C55]" /> {{ selectedAppointment.room?.name || 'Sin asignar' }}</dd></div><div class="sm:col-span-2"><dt class="text-xs text-[#64748B]">Motivo / procedimiento</dt><dd class="mt-1 text-sm font-semibold text-[#263633]">{{ selectedAppointment.appointment_type?.name || selectedAppointment.reason || 'Consulta general' }}</dd></div></dl>

                <form v-if="isRescheduling" class="space-y-4 rounded-lg border border-[#98A2B3] bg-[#FAF8FF] p-4" @submit.prevent="submitReschedule"><div class="flex items-center justify-between"><h3 class="font-semibold text-[#131B2E]">Reprogramar cita</h3><button type="button" class="text-xs font-semibold text-[#005C55]" @click="isRescheduling = false">Cerrar</button></div><div class="grid gap-3 sm:grid-cols-2"><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Nueva fecha y hora</span><input v-model="rescheduleForm.start_time" required type="datetime-local" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm" /></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Duración</span><select v-model="rescheduleForm.duration_minutes" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option :value="30">30 min</option><option :value="45">45 min</option><option :value="60">60 min</option><option :value="90">90 min</option></select></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Profesional</span><select v-model="rescheduleForm.professional_id" required class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Sillón</span><select v-model="rescheduleForm.room_id" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm"><option value="">Sin asignar</option><option v-for="room in appointmentRooms" :key="room.id" :value="room.id">{{ room.name }}</option></select></label><label class="sm:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Motivo actualizado</span><input v-model="rescheduleForm.reason" class="h-10 w-full border border-[#BDC9C6] bg-white px-3 text-sm" /></label></div><p v-if="Object.keys(rescheduleForm.errors).length" class="text-xs font-medium text-[#B42318]">Revisa la disponibilidad y los datos de la nueva reserva.</p><button :disabled="rescheduleForm.processing" class="h-10 w-full bg-[#005C55] text-sm font-semibold text-white disabled:opacity-60">Confirmar reprogramación</button></form>

                <form v-if="isCancelling" class="space-y-3 rounded-lg border border-[#FDA29B] bg-[#FFF5F4] p-4" @submit.prevent="submitCancellation"><div class="flex items-center justify-between"><h3 class="font-semibold text-[#912018]">Cancelar cita</h3><button type="button" class="text-xs font-semibold text-[#912018]" @click="isCancelling = false">Cerrar</button></div><label><span class="mb-1 block text-xs font-semibold text-[#912018]">Motivo de cancelación</span><textarea v-model="cancellationForm.cancellation_reason" required rows="3" maxlength="500" class="w-full border border-[#FDA29B] bg-white px-3 py-2 text-sm" placeholder="Registra por qué se cancela esta cita"></textarea></label><p v-if="cancellationForm.errors.cancellation_reason" class="text-xs font-medium text-[#B42318]">{{ cancellationForm.errors.cancellation_reason }}</p><button :disabled="cancellationForm.processing" class="h-10 w-full bg-[#B42318] text-sm font-semibold text-white disabled:opacity-60">Confirmar cancelación</button></form>
            </div>
            <footer v-if="!isRescheduling && !isCancelling" class="sticky bottom-0 grid gap-2 border-t border-[#D8E0DE] bg-white px-6 py-4 sm:grid-cols-2"><button v-if="['scheduled','confirmed'].includes(selectedAppointment.status)" class="inline-flex h-10 items-center justify-center gap-2 bg-[#2458C6] text-sm font-semibold text-white" @click="updateStatus(selectedAppointment, 'checked_in')"><UserCheck class="h-4 w-4" /> Registrar llegada</button><button v-if="['checked_in','waiting'].includes(selectedAppointment.status)" class="inline-flex h-10 items-center justify-center gap-2 bg-[#007D73] text-sm font-semibold text-white" @click="updateStatus(selectedAppointment, 'in_progress')"><Play class="h-4 w-4" /> Iniciar consulta</button><button v-if="selectedAppointment.status === 'in_progress'" class="inline-flex h-10 items-center justify-center gap-2 bg-[#005C55] text-sm font-semibold text-white" @click="updateStatus(selectedAppointment, 'completed')"><Check class="h-4 w-4" /> Completar</button><button v-if="!['completed','cancelled','no_show'].includes(selectedAppointment.status)" class="inline-flex h-10 items-center justify-center gap-2 border border-[#BDC9C6] text-sm font-semibold text-[#263633]" @click="openReschedule(selectedAppointment)"><RotateCcw class="h-4 w-4" /> Reprogramar</button><button v-if="!['completed','cancelled','no_show'].includes(selectedAppointment.status)" class="inline-flex h-10 items-center justify-center gap-2 border border-[#FDA29B] text-sm font-semibold text-[#B42318]" @click="openCancellation(selectedAppointment)"><X class="h-4 w-4" /> Cancelar cita</button></footer>
        </aside>
</div>
    </ClinicLayout>
</template>
