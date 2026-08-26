<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { computed, nextTick, ref, watch } from 'vue'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import OdontogramChart from '@/Components/Odontogram/OdontogramChart.vue'
import PeriodontalPanel from '@/Components/Odontogram/PeriodontalPanel.vue'
import { AlertTriangle, ArrowLeft, CheckCircle2, ChevronRight, CircleDot, Clock3, FileClock, History, Images, ReceiptText, RotateCcw, Save, ShieldCheck, Stethoscope } from 'lucide-vue-next'

defineOptions({ layout: ClinicLayout })

interface MedicalHistory { allergies: string[] | null; systemic_conditions: string[] | null }
interface PatientDetails { id: string; record_number: string; full_name: string; age: number | null; medical_history: MedicalHistory | null }
interface OdontogramDetails { id: string; patient_id: string; type: 'adult' | 'pediatric' | 'mixed'; caries_risk_level: string | null; caries_risk_factors: string[] | null }
type SurfaceKey = 'all' | 'vestibular' | 'lingual_palatal' | 'mesial' | 'distal' | 'occlusal_incisal'
type ConditionKey = 'caries' | 'restored_composite' | 'restored_amalgam' | 'crown' | 'endodontic' | 'missing' | 'implant' | 'prosthesis' | 'sealant' | 'fracture' | 'healthy'
type LifecycleKey = 'initial_diagnosis' | 'planned' | 'approved' | 'completed'
type EntryType = 'finding' | 'diagnosis' | 'procedure' | 'device' | 'anatomical_state'
type ClinicalStatus = 'active' | 'inactive' | 'resolved' | 'recurrence'
type VerificationStatus = 'provisional' | 'differential' | 'confirmed' | 'refuted' | 'entered_in_error'
interface ConditionSummary { id: string; condition: ConditionKey; surface: SurfaceKey; surfaces?: SurfaceKey[]; entry_type?: EntryType; clinical_status?: ClinicalStatus; verification_status?: VerificationStatus; clinical_code?: string | null; clinical_display?: string | null; supersedes_entry_id?: string | null; amendment_reason?: string | null; lifecycle_state: LifecycleKey; recorded_at: string; notes: string | null }
interface ToothData { tooth_number: number; conditions: ConditionSummary[]; surfaces: Partial<Record<SurfaceKey, { condition: ConditionKey; lifecycle_state: LifecycleKey }>>; latest_state: LifecycleKey }
interface EntryLog extends ConditionSummary { tooth_number: number; recorded_by?: { name: string } | null; encounter?: { id: string; encounter_date?: string } | null }
interface PeriodontalMeasurement { tooth_number: number; site: 'mb' | 'b' | 'db' | 'ml' | 'l' | 'dl'; probing_depth: number | null; recession: number | null; bleeding: boolean; plaque: boolean; suppuration: boolean; mobility: number | null; furcation: number | null; is_implant: boolean }
interface PeriodontalExam { id: string; status: string; recorded_at: string; measurements: PeriodontalMeasurement[] }
interface DentalFile { id: string; category: string; original_name: string; mime_type: string; tooth_number: number | null; odontogram_entry_id: string | null; taken_at: string | null; created_at: string }

const props = defineProps<{ patient: PatientDetails; odontogram: OdontogramDetails; matrix: Record<number, ToothData>; entries: EntryLog[]; periodontalExam: PeriodontalExam | null; dentalFiles: DentalFile[] }>()

const permanentUpper = [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]]
const permanentLower = [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]]
const primaryUpper = [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]]
const primaryLower = [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]]

const conditions: Array<{ value: ConditionKey; label: string; short: string; dot: string }> = [
    { value: 'caries', label: 'Caries activa', short: 'Caries', dot: 'bg-[#E5484D]' },
    { value: 'restored_composite', label: 'Restauración en resina', short: 'Resina', dot: 'bg-[#3478F6]' },
    { value: 'restored_amalgam', label: 'Restauración en amalgama', short: 'Amalgama', dot: 'bg-[#667085]' },
    { value: 'crown', label: 'Corona', short: 'Corona', dot: 'bg-[#EAAA08]' },
    { value: 'endodontic', label: 'Endodoncia', short: 'Endodoncia', dot: 'bg-[#8B5CF6]' },
    { value: 'missing', label: 'Pieza ausente', short: 'Ausente', dot: 'bg-[#CBD5E1]' },
    { value: 'implant', label: 'Implante', short: 'Implante', dot: 'bg-[#12B76A]' },
    { value: 'prosthesis', label: 'Prótesis', short: 'Prótesis', dot: 'bg-[#F79009]' },
    { value: 'sealant', label: 'Sellante', short: 'Sellante', dot: 'bg-[#14B8A6]' },
    { value: 'fracture', label: 'Fractura', short: 'Fractura', dot: 'bg-[#B42318]' },
    { value: 'healthy', label: 'Sano / sin hallazgo', short: 'Sano', dot: 'bg-white border border-[#9AAEAA]' },
]
const surfaces: Array<{ value: SurfaceKey; label: string; short: string }> = [
    { value: 'all', label: 'Pieza completa', short: 'Toda' }, { value: 'mesial', label: 'Mesial', short: 'M' },
    { value: 'occlusal_incisal', label: 'Oclusal / incisal', short: 'O/I' }, { value: 'distal', label: 'Distal', short: 'D' },
    { value: 'vestibular', label: 'Vestibular', short: 'V' }, { value: 'lingual_palatal', label: 'Lingual / palatina', short: 'L/P' },
]
const lifecycleOptions: Array<{ value: LifecycleKey; label: string; description: string }> = [
    { value: 'initial_diagnosis', label: 'Diagnóstico', description: 'Hallazgo clínico inicial' },
    { value: 'planned', label: 'Planificado', description: 'Incluido en propuesta terapéutica' },
    { value: 'approved', label: 'Aprobado', description: 'Autorizado por el paciente' },
    { value: 'completed', label: 'Realizado', description: 'Tratamiento ejecutado' },
]
const entryTypes: Array<{ value: EntryType; label: string }> = [{ value: 'finding', label: 'Hallazgo' }, { value: 'diagnosis', label: 'Diagnóstico' }, { value: 'procedure', label: 'Procedimiento' }, { value: 'device', label: 'Dispositivo / implante' }, { value: 'anatomical_state', label: 'Estado anatómico' }]
const clinicalStatuses: Array<{ value: ClinicalStatus; label: string }> = [{ value: 'active', label: 'Activo' }, { value: 'inactive', label: 'Inactivo' }, { value: 'resolved', label: 'Resuelto' }, { value: 'recurrence', label: 'Recurrencia' }]
const verificationStatuses: Array<{ value: VerificationStatus; label: string }> = [{ value: 'provisional', label: 'Provisional' }, { value: 'differential', label: 'Diferencial' }, { value: 'confirmed', label: 'Confirmado' }, { value: 'refuted', label: 'Descartado' }, { value: 'entered_in_error', label: 'Registrado por error' }]

const dentition = ref<'permanent' | 'primary'>(props.odontogram.type === 'pediatric' ? 'primary' : 'permanent')
const selectedTooth = ref(dentition.value === 'primary' ? 51 : 11)
const historyScope = ref<'selected' | 'all'>('selected')
const historySection = ref<HTMLElement | null>(null)
const savedEntryNotice = ref(false)
const loadedEntry = ref<EntryLog | null>(null)
const amendmentMode = ref(false)
const form = useForm<{ tooth_number: number; surfaces: SurfaceKey[]; condition: ConditionKey; entry_type: EntryType; clinical_status: ClinicalStatus; verification_status: VerificationStatus; lifecycle_state: LifecycleKey; notes: string; supersedes_entry_id: string | null; amendment_reason: string }>({ tooth_number: selectedTooth.value, surfaces: ['all'], condition: 'caries', entry_type: 'diagnosis', clinical_status: 'active', verification_status: 'confirmed', lifecycle_state: 'initial_diagnosis', notes: '', supersedes_entry_id: null, amendment_reason: '' })
const riskForm = useForm<{ caries_risk_level: string; caries_risk_factors: string[] }>({ caries_risk_level: props.odontogram.caries_risk_level || 'moderate', caries_risk_factors: props.odontogram.caries_risk_factors || [] })

const arches = computed(() => dentition.value === 'permanent' ? { upper: permanentUpper, lower: permanentLower, count: 32, label: 'Permanente' } : { upper: primaryUpper, lower: primaryLower, count: 20, label: 'Temporal' })
const selectedData = computed(() => props.matrix[selectedTooth.value])
const selectedEntries = computed(() => props.entries.filter((entry) => entry.tooth_number === selectedTooth.value))
const visibleEntries = computed(() => historyScope.value === 'selected' ? selectedEntries.value : props.entries)
const allergies = computed(() => props.patient.medical_history?.allergies || [])
const systemicConditions = computed(() => props.patient.medical_history?.systemic_conditions || [])
const selectedDentalFiles = computed(() => props.dentalFiles.filter((file) => !file.tooth_number || file.tooth_number === selectedTooth.value))
const quoteUrl = computed(() => {
    const entry = loadedEntry.value || latestEntryFor(selectedTooth.value)
    const query = new URLSearchParams({ tooth_number: String(selectedTooth.value), surface: entry?.surfaces?.[0] || entry?.surface || 'all' })
    if (entry) {
        query.set('odontogram_entry_id', entry.id)
        query.set('clinical_note', `Pieza ${selectedTooth.value}: ${conditionLabel(entry.condition)}${entry.notes ? ` — ${entry.notes}` : ''}`)
    }
    return appUrl(`/patients/${props.patient.id}/quotes/create?${query.toString()}`)
})

function latestEntryFor(tooth: number): EntryLog | undefined { return props.entries.find((entry) => entry.tooth_number === tooth) }
function populateFormFromTooth(tooth: number) {
    const entry = latestEntryFor(tooth)
    form.tooth_number = tooth
    form.clearErrors()
    loadedEntry.value = entry || null
    amendmentMode.value = false
    form.supersedes_entry_id = null
    form.amendment_reason = ''

    if (!entry) {
        form.surfaces = ['all']
        form.condition = 'caries'
        form.entry_type = 'diagnosis'
        form.clinical_status = 'active'
        form.verification_status = 'confirmed'
        form.lifecycle_state = 'initial_diagnosis'
        form.notes = ''
        return
    }

    form.surfaces = entry.surfaces?.length ? [...entry.surfaces] : [entry.surface]
    form.condition = entry.condition
    form.entry_type = entry.entry_type || entryTypeForCondition(entry.condition)
    form.clinical_status = entry.clinical_status || 'active'
    form.verification_status = entry.verification_status || 'confirmed'
    form.lifecycle_state = entry.lifecycle_state
    form.notes = entry.notes || ''
}
function selectTooth(tooth: number) {
    selectedTooth.value = tooth
    historyScope.value = 'selected'
    savedEntryNotice.value = false
    populateFormFromTooth(tooth)
}
function switchDentition(value: 'permanent' | 'primary') { dentition.value = value; selectTooth(value === 'permanent' ? 11 : 51) }
function conditionLabel(condition?: ConditionKey) { return conditions.find((item) => item.value === condition)?.short || 'Sin hallazgos' }
function surfaceLabel(surface: SurfaceKey) { return surfaces.find((item) => item.value === surface)?.label || surface }
function lifecycleLabel(lifecycle: LifecycleKey) { return lifecycleOptions.find((item) => item.value === lifecycle)?.label || lifecycle }
function lifecycleClass(lifecycle: LifecycleKey) { if (lifecycle === 'completed') return 'bg-[#ECFDF3] text-[#027A48] border-[#ABEFC6]'; if (lifecycle === 'approved') return 'bg-[#EFF8FF] text-[#175CD3] border-[#B2DDFF]'; if (lifecycle === 'planned') return 'bg-[#FFFAEB] text-[#B54708] border-[#FEDF89]'; return 'bg-[#FFF1F0] text-[#B42318] border-[#FECDCA]' }
function formatDate(value: string) { return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) }
function entryTypeForCondition(condition: ConditionKey): EntryType { if (['caries', 'fracture'].includes(condition)) return 'diagnosis'; if (condition === 'missing') return 'anatomical_state'; if (condition === 'implant') return 'device'; if (['restored_composite', 'restored_amalgam', 'crown', 'endodontic', 'prosthesis', 'sealant'].includes(condition)) return 'procedure'; return 'finding' }
function toggleSurface(surface: SurfaceKey) { if (surface === 'all') { form.surfaces = ['all']; return } const next = form.surfaces.filter((value) => value !== 'all'); form.surfaces = next.includes(surface) ? next.filter((value) => value !== surface) : [...next, surface]; if (!form.surfaces.length) form.surfaces = ['all'] }
function toggleAmendment() { amendmentMode.value = !amendmentMode.value; form.supersedes_entry_id = amendmentMode.value ? loadedEntry.value?.id || null : null; if (!amendmentMode.value) form.amendment_reason = '' }
function resetForm() { loadedEntry.value = null; amendmentMode.value = false; form.surfaces = ['all']; form.condition = 'caries'; form.entry_type = 'diagnosis'; form.clinical_status = 'active'; form.verification_status = 'confirmed'; form.lifecycle_state = 'initial_diagnosis'; form.notes = ''; form.supersedes_entry_id = null; form.amendment_reason = ''; form.clearErrors() }
function submitRisk() { riskForm.post(appUrl(`/patients/${props.patient.id}/odontogram/caries-risk`), { preserveScroll: true }) }
function submitEntry() {
    form.tooth_number = selectedTooth.value
    savedEntryNotice.value = false
    form.post(appUrl(`/patients/${props.patient.id}/odontogram/entries`), {
        preserveScroll: true,
        onSuccess: () => {
            historyScope.value = 'selected'
            savedEntryNotice.value = true
            nextTick(() => {
                populateFormFromTooth(selectedTooth.value)
                historySection.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
            })
        },
    })
}
watch(() => form.condition, (condition) => { if (!loadedEntry.value || !amendmentMode.value) form.entry_type = entryTypeForCondition(condition) })
</script>

<template>
    <Head :title="`Odontograma — ${patient.full_name}`" />
    <main class="space-y-5 bg-[#F8FAFC] p-4 text-[#131B2E] md:p-6">
        <nav class="flex flex-wrap items-center gap-2 text-xs text-[#64748B]"><Link :href="appUrl('/patients')" class="hover:text-[#005C55]">Pacientes</Link><ChevronRight class="h-3.5 w-3.5" /><Link :href="appUrl(`/patients/${patient.id}`)" class="hover:text-[#005C55]">{{ patient.full_name }}</Link><ChevronRight class="h-3.5 w-3.5" /><span class="font-semibold text-[#344054]">Odontograma</span></nav>
        <header class="flex flex-col gap-4 border border-[#D8E0DE] bg-white p-4 shadow-[0_4px_12px_rgba(15,23,42,0.04)] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3"><div class="grid h-12 w-12 shrink-0 place-items-center bg-[#D8ECE9] font-bold text-[#005C55]">{{ patient.full_name.charAt(0) }}</div><div><div class="flex flex-wrap items-baseline gap-2"><h1 class="text-xl font-bold">{{ patient.full_name }}</h1><span class="font-mono text-xs text-[#64748B]">{{ patient.record_number }}</span></div><p class="mt-1 text-xs text-[#64748B]">{{ patient.age ? `${patient.age} años · ` : '' }}Registro dental FDI con trazabilidad clínica</p></div></div>
            <div class="flex flex-wrap items-center gap-2"><span v-for="allergy in allergies" :key="allergy" class="inline-flex items-center gap-1.5 border border-[#FDA29B] bg-[#FEF3F2] px-2.5 py-1.5 text-xs font-semibold text-[#B42318]"><AlertTriangle class="h-3.5 w-3.5" />Alergia: {{ allergy }}</span><span v-for="condition in systemicConditions" :key="condition" class="inline-flex items-center gap-1.5 border border-[#FEDF89] bg-[#FFFAEB] px-2.5 py-1.5 text-xs font-semibold text-[#B54708]"><CircleDot class="h-3.5 w-3.5" />{{ condition }}</span><Link :href="appUrl(`/patients/${patient.id}`)" class="inline-flex h-9 items-center gap-2 border border-[#9AAEAA] bg-white px-3 text-xs font-semibold text-[#344054] hover:bg-[#F2F4F7]"><ArrowLeft class="h-4 w-4" />Ficha 360</Link></div>
        </header>

        <section class="grid gap-5 2xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0 border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
                <header class="flex flex-col gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="flex items-center gap-2 text-sm font-bold"><Stethoscope class="h-4 w-4 text-[#005C55]" />Odontograma interactivo</h2><p class="mt-0.5 text-xs text-[#64748B]">Selecciona una pieza para documentar sus superficies.</p></div><div class="inline-flex w-fit border border-[#9AAEAA] bg-white p-0.5"><button type="button" class="h-8 px-3 text-xs font-semibold" :class="dentition === 'permanent' ? 'bg-[#005C55] text-white' : 'text-[#52615E]'" @click="switchDentition('permanent')">Permanente · 32</button><button type="button" class="h-8 px-3 text-xs font-semibold" :class="dentition === 'primary' ? 'bg-[#005C55] text-white' : 'text-[#52615E]'" @click="switchDentition('primary')">Temporal · 20</button></div></header>
                <div class="overflow-x-auto bg-[#FBFCFC] p-4 sm:p-6 lg:p-8">
                    <OdontogramChart
                        :matrix="matrix"
                        :dentition="dentition"
                        :selected-tooth="selectedTooth"
                        @select="selectTooth"
                    />
                </div>
                <footer class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-[#D8E0DE] px-4 py-3"><span class="mr-1 text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Convenciones</span><span v-for="condition in conditions.slice(0, 8)" :key="condition.value" class="inline-flex items-center gap-1.5 text-[11px] text-[#52615E]"><i class="h-2.5 w-2.5 rounded-full" :class="condition.dot"></i>{{ condition.short }}</span></footer>
            </div>

            <aside class="h-fit border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)] 2xl:sticky 2xl:top-20">
                <header class="border-b border-[#D8E0DE] p-4"><p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#64748B]">Pieza seleccionada</p><div class="mt-2 flex items-center justify-between gap-3"><div class="flex items-center gap-3"><strong class="font-mono text-4xl text-[#005C55]">{{ selectedTooth }}</strong><div><p class="text-sm font-bold">{{ dentition === 'permanent' ? 'Dentición permanente' : 'Dentición temporal' }}</p><p class="text-xs text-[#64748B]">{{ selectedEntries.length }} registro{{ selectedEntries.length === 1 ? '' : 's' }} histórico{{ selectedEntries.length === 1 ? '' : 's' }}</p></div></div><span class="border px-2 py-1 text-[10px] font-bold" :class="selectedData ? lifecycleClass(selectedData.latest_state) : 'border-[#D8E0DE] bg-[#F2F4F7] text-[#667085]'">{{ selectedData ? lifecycleLabel(selectedData.latest_state) : 'Sin registro' }}</span></div></header>
                <form class="space-y-4 p-4" @submit.prevent="submitEntry">
                    <div v-if="loadedEntry" class="flex items-start gap-2 border border-[#B2DDFF] bg-[#EFF8FF] p-3 text-[11px] leading-4 text-[#175CD3]" role="status"><FileClock class="mt-0.5 h-4 w-4 shrink-0" /><span><strong>Último registro cargado:</strong> {{ formatDate(loadedEntry.recorded_at) }} · Puedes modificar estos datos; al guardar se creará una nueva evolución sin alterar la anterior.</span></div>
                    <div v-else class="flex items-start gap-2 border border-[#D8E0DE] bg-[#F7FAF9] p-3 text-[11px] leading-4 text-[#52615E]"><CircleDot class="mt-0.5 h-4 w-4 shrink-0" /><span>Esta pieza no tiene registros previos. El formulario está listo para documentar el primer hallazgo.</span></div>
                    <fieldset><legend class="mb-2 text-xs font-bold text-[#344054]">Superficies afectadas <span class="font-normal text-[#64748B]">· selección múltiple</span></legend><div class="grid grid-cols-3 gap-2"><button v-for="surface in surfaces" :key="surface.value" type="button" class="min-h-10 border px-2 py-1.5 text-xs font-semibold" :class="form.surfaces.includes(surface.value) ? 'border-[#005C55] bg-[#D8ECE9] text-[#005C55]' : 'border-[#BDC9C6] bg-white text-[#52615E]'" @click="toggleSurface(surface.value)"><span class="block font-mono font-bold">{{ surface.short }}</span><span class="text-[9px] font-normal">{{ surface.label }}</span></button></div><p v-if="form.errors.surfaces" class="mt-1 text-xs text-[#B42318]">{{ form.errors.surfaces }}</p></fieldset>
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Condición clínica</span><select v-model="form.condition" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm outline-none focus:border-[#005C55]"><option v-for="condition in conditions" :key="condition.value" :value="condition.value">{{ condition.label }}</option></select><p v-if="form.errors.condition" class="mt-1 text-xs text-[#B42318]">{{ form.errors.condition }}</p></label>
                    <div class="grid grid-cols-2 gap-2"><label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Tipo clínico</span><select v-model="form.entry_type" class="h-10 w-full border border-[#9AAEAA] bg-white px-2 text-xs"><option v-for="type in entryTypes" :key="type.value" :value="type.value">{{ type.label }}</option></select></label><label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Verificación</span><select v-model="form.verification_status" class="h-10 w-full border border-[#9AAEAA] bg-white px-2 text-xs"><option v-for="status in verificationStatuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></label></div>
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Estado clínico</span><select v-model="form.clinical_status" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm"><option v-for="status in clinicalStatuses" :key="status.value" :value="status.value">{{ status.label }}</option></select></label>
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Estado asistencial</span><select v-model="form.lifecycle_state" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm outline-none focus:border-[#005C55]"><option v-for="state in lifecycleOptions" :key="state.value" :value="state.value">{{ state.label }} — {{ state.description }}</option></select><p v-if="form.errors.lifecycle_state" class="mt-1 text-xs text-[#B42318]">{{ form.errors.lifecycle_state }}</p></label>
                    <label class="block"><span class="mb-1.5 block text-xs font-bold text-[#344054]">Nota clínica</span><textarea v-model="form.notes" rows="3" maxlength="500" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm outline-none placeholder:text-[#98A2B3] focus:border-[#005C55]" placeholder="Hallazgo, profundidad, material o conducta…"></textarea><span class="mt-1 block text-right font-mono text-[10px] text-[#98A2B3]">{{ form.notes.length }}/500</span><p v-if="form.errors.notes" class="mt-1 text-xs text-[#B42318]">{{ form.errors.notes }}</p></label>
                    <div v-if="loadedEntry" class="border border-[#D8E0DE] bg-[#FBFCFC] p-3"><label class="flex cursor-pointer items-center gap-2 text-xs font-semibold"><input :checked="amendmentMode" type="checkbox" class="h-4 w-4 accent-[#005C55]" @change="toggleAmendment" />Este registro corrige al último guardado</label><label v-if="amendmentMode" class="mt-3 block"><span class="mb-1 block text-xs font-bold">Motivo de la corrección</span><textarea v-model="form.amendment_reason" rows="2" class="w-full border border-[#9AAEAA] px-2 py-1.5 text-xs" placeholder="Explique por qué se corrige el registro anterior"></textarea><p v-if="form.errors.amendment_reason" class="mt-1 text-xs text-[#B42318]">{{ form.errors.amendment_reason }}</p></label></div>
                    <div class="flex items-start gap-2 border border-[#B2DDFF] bg-[#EFF8FF] p-3 text-[11px] leading-4 text-[#175CD3]"><ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" /><span>Cada cambio crea una entrada histórica. Las correcciones conservan el registro original.</span></div><Link :href="quoteUrl" class="inline-flex h-10 w-full items-center justify-center gap-2 border border-[#007D73] bg-[#F1FAF8] px-4 text-sm font-bold text-[#006B63]"><ReceiptText class="h-4 w-4" />Crear presupuesto para pieza {{ selectedTooth }}</Link><div class="flex gap-2"><button type="button" class="inline-flex h-10 items-center justify-center gap-2 border border-[#9AAEAA] px-3 text-xs font-semibold text-[#52615E]" @click="resetForm"><RotateCcw class="h-3.5 w-3.5" />Limpiar</button><button type="submit" :disabled="form.processing" class="inline-flex h-10 flex-1 items-center justify-center gap-2 bg-[#005C55] px-4 text-sm font-bold text-white disabled:cursor-wait disabled:opacity-60"><Save class="h-4 w-4" />{{ form.processing ? 'Guardando…' : 'Guardar registro' }}</button></div>
                </form>
            </aside>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <PeriodontalPanel :patient-id="patient.id" :selected-tooth="selectedTooth" :exam="periodontalExam" />
            <div class="space-y-5">
                <form class="border border-[#D8E0DE] bg-white p-4" @submit.prevent="submitRisk"><h2 class="text-sm font-bold">Riesgo de caries</h2><p class="mt-1 text-xs text-[#64748B]">Evaluación global del paciente.</p><select v-model="riskForm.caries_risk_level" class="mt-3 h-10 w-full border border-[#9AAEAA] px-3 text-sm"><option value="low">Bajo</option><option value="moderate">Moderado</option><option value="high">Alto</option><option value="extreme">Extremo</option></select><button type="submit" :disabled="riskForm.processing" class="mt-3 h-9 w-full bg-[#005C55] px-3 text-xs font-bold text-white">Actualizar riesgo</button></form>
                <section class="border border-[#D8E0DE] bg-white"><header class="border-b border-[#D8E0DE] p-4"><h2 class="flex items-center gap-2 text-sm font-bold"><Images class="h-4 w-4 text-[#005C55]" />Imágenes de la pieza {{ selectedTooth }}</h2><p class="mt-1 text-xs text-[#64748B]">Radiografías y fotografías vinculadas.</p></header><div v-if="selectedDentalFiles.length" class="divide-y divide-[#E4E7EC]"><a v-for="file in selectedDentalFiles" :key="file.id" :href="appUrl(`/patient-files/${file.id}/view`)" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 p-3 text-xs hover:bg-[#F7FAF9]"><span class="truncate font-semibold">{{ file.original_name }}</span><span class="shrink-0 text-[#006B63]">Ver →</span></a></div><div v-else class="p-4 text-xs text-[#64748B]">No hay imágenes vinculadas a esta pieza.</div><Link :href="appUrl(`/patients/${patient.id}`)" class="block border-t border-[#D8E0DE] p-3 text-center text-xs font-bold text-[#006B63]">Adjuntar desde la ficha 360</Link></section>
            </div>
        </section>

        <section ref="historySection" class="scroll-mt-20 border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
<header class="flex flex-col gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="flex items-center gap-2 text-sm font-bold"><History class="h-4 w-4 text-[#005C55]" />Trazabilidad del odontograma</h2><p class="mt-0.5 text-xs text-[#64748B]">Diagnósticos y transiciones conservados en orden cronológico inverso.</p></div><div class="inline-flex w-fit border border-[#9AAEAA] bg-white p-0.5"><button type="button" class="h-8 px-3 text-xs font-semibold" :class="historyScope === 'selected' ? 'bg-[#005C55] text-white' : 'text-[#52615E]'" @click="historyScope = 'selected'">Pieza {{ selectedTooth }}</button><button type="button" class="h-8 px-3 text-xs font-semibold" :class="historyScope === 'all' ? 'bg-[#005C55] text-white' : 'text-[#52615E]'" @click="historyScope = 'all'">Todo el historial</button></div></header>
            <div v-if="savedEntryNotice" class="flex items-start gap-2 border-b border-[#ABEFC6] bg-[#ECFDF3] px-4 py-3 text-xs text-[#027A48]" role="status"><CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" /><span><strong>Registro guardado.</strong> Aquí puedes consultar la ficha y todo el historial de la pieza {{ selectedTooth }}.</span></div>
            <div v-if="visibleEntries.length" class="divide-y divide-[#E4E7EC]"><article v-for="entry in visibleEntries" :key="entry.id" class="grid gap-3 px-4 py-3 sm:grid-cols-[70px_minmax(160px,1fr)_minmax(170px,1.4fr)_auto] sm:items-center"><div><span class="font-mono text-lg font-bold text-[#005C55]">{{ entry.tooth_number }}</span><p class="text-[10px] text-[#98A2B3]">Pieza FDI</p></div><div><p class="text-sm font-semibold">{{ conditionLabel(entry.condition) }}</p><p class="mt-0.5 text-xs text-[#64748B]">{{ (entry.surfaces || [entry.surface]).map(surfaceLabel).join(' · ') }}</p><p class="mt-1 text-[10px] font-bold uppercase text-[#006B63]">{{ entryTypes.find(type => type.value === entry.entry_type)?.label || 'Hallazgo' }} · {{ verificationStatuses.find(status => status.value === entry.verification_status)?.label || 'Confirmado' }}</p></div><div><p class="text-xs text-[#344054]">{{ entry.notes || 'Sin observaciones adicionales.' }}</p><p v-if="entry.amendment_reason" class="mt-1 text-[10px] font-semibold text-[#B54708]">Corrección: {{ entry.amendment_reason }}</p><p class="mt-1 flex items-center gap-1 text-[10px] text-[#98A2B3]"><Clock3 class="h-3 w-3" />{{ formatDate(entry.recorded_at) }} · {{ entry.recorded_by?.name || 'Usuario del sistema' }}</p></div><span class="w-fit border px-2 py-1 text-[10px] font-bold" :class="lifecycleClass(entry.lifecycle_state)">{{ lifecycleLabel(entry.lifecycle_state) }}</span></article></div>
            <div v-else class="grid min-h-40 place-items-center p-6 text-center"><div><FileClock class="mx-auto h-8 w-8 text-[#98A2B3]" /><p class="mt-2 text-sm font-semibold text-[#344054]">Sin registros para esta pieza</p><p class="mt-1 text-xs text-[#64748B]">Selecciona una superficie y documenta el primer hallazgo clínico.</p></div></div>
        </section>
        <footer class="flex flex-wrap items-center justify-between gap-2 pb-3 text-[11px] text-[#64748B]"><span class="inline-flex items-center gap-1.5"><Stethoscope class="h-3.5 w-3.5" />Notación FDI · {{ arches.label }} · {{ arches.count }} piezas</span><span class="inline-flex items-center gap-1.5"><CheckCircle2 class="h-3.5 w-3.5 text-[#027A48]" />{{ entries.length }} eventos clínicos trazables</span></footer>
    </main>
</template>
