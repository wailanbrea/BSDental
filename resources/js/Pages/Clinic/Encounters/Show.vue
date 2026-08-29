<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ref } from 'vue'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Activity, AlertTriangle, ArrowLeft, CheckCircle2, ClipboardCheck, FileEdit, FileSignature, HeartPulse, LockKeyhole, Pencil, Pill, Save, ShieldAlert, ShieldCheck, Stethoscope, X } from 'lucide-vue-next'

interface PatientDetails { id: string; record_number: string; full_name: string }
interface ProfessionalDetails { id: string; full_name: string }
interface DiagnosisItem { id: string; code: string | null; description: string; type: string }
interface EvolutionDetails {
    subjective: string | null; objective: string | null; assessment: string | null; plan: string | null
    treatment_performed: string | null; recommendations: string | null
}
interface PrescriptionItem {
    id: string; medication_name: string; dosage: string; frequency: string; duration: string; instructions: string | null
}
interface AmendmentItem {
    id: string; reason: string; amended_content: Record<string, unknown>; amended_at: string; integrity_hash: string
    amended_by: { name: string }
}
interface EncounterDetails {
    id: string; patient_id: string; encounter_date: string; chief_complaint: string | null; physical_examination: string | null
    vital_signs: { blood_pressure?: string; heart_rate?: number | string; temperature?: number | string; oxygen_saturation?: number | string } | null
    status: 'draft' | 'finalized' | 'amended'; finalized_at: string | null; integrity_hash: string | null
    patient: PatientDetails; professional: ProfessionalDetails; evolution: EvolutionDetails | null
    diagnoses: DiagnosisItem[]; prescriptions: PrescriptionItem[]; amendments: AmendmentItem[]; finalized_by?: { name: string }
}
interface IntegrityStatus { status: 'not_sealed' | 'verified' | 'legacy' | 'mismatch'; algorithm: string; checked_at: string }

const props = defineProps<{ encounter: EncounterDetails; integrity: IntegrityStatus }>()
const isEditing = ref(false)
const isAmendmentModal = ref(false)
const isFinalizeModal = ref(false)
const finalizeForm = useForm<{ error?: string }>({})
const editForm = useForm({
    chief_complaint: props.encounter.chief_complaint || '',
    physical_examination: props.encounter.physical_examination || '',
    vital_signs: {
        blood_pressure: props.encounter.vital_signs?.blood_pressure || '',
        heart_rate: props.encounter.vital_signs?.heart_rate || '',
        temperature: props.encounter.vital_signs?.temperature || '',
        oxygen_saturation: props.encounter.vital_signs?.oxygen_saturation || '',
    },
    subjective: props.encounter.evolution?.subjective || '',
    objective: props.encounter.evolution?.objective || '',
    assessment: props.encounter.evolution?.assessment || '',
    plan: props.encounter.evolution?.plan || '',
    treatment_performed: props.encounter.evolution?.treatment_performed || '',
    recommendations: props.encounter.evolution?.recommendations || '',
})
const amendmentForm = useForm({ reason: '', amended_content: { note: '' } })

function formatDate(value: string | null) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
function statusLabel(status: EncounterDetails['status']) {
    return ({ draft: 'Borrador abierto', finalized: 'Finalizado', amended: 'Finalizado con enmiendas' })[status]
}
function amendmentNote(amendment: AmendmentItem) {
    return typeof amendment.amended_content.note === 'string' ? amendment.amended_content.note : JSON.stringify(amendment.amended_content)
}
function saveDraft() {
    editForm.put(appUrl(`/encounters/${props.encounter.id}`), { preserveScroll: true, onSuccess: () => { isEditing.value = false } })
}
function finalizeEncounter() {
    finalizeForm.post(appUrl(`/encounters/${props.encounter.id}/finalize`), { preserveScroll: true, onSuccess: () => { isFinalizeModal.value = false } })
}
function submitAmendment() {
    amendmentForm.post(appUrl(`/encounters/${props.encounter.id}/amend`), {
        preserveScroll: true,
        onSuccess: () => { isAmendmentModal.value = false; amendmentForm.reset() },
    })
}
</script>

<template>
    <Head :title="`Evolución clínica — ${encounter.patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1500px] space-y-5">
            <header class="flex flex-col gap-4 border-b border-[#D8E0DE] pb-5 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <Link :href="`/patients/${encounter.patient_id}`" class="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-[#006B63]"><ArrowLeft class="h-4 w-4" /> Volver a Ficha 360</Link>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#64748B]">Historia clínica · {{ encounter.patient.record_number }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-3"><h1 class="text-2xl font-bold text-[#131B2E]">Evolución odontológica</h1><span class="border px-2.5 py-1 text-xs font-bold" :class="encounter.status === 'draft' ? 'border-[#FEC84B] bg-[#FFFAEB] text-[#93370D]' : 'border-[#B7D9D4] bg-[#F1FAF8] text-[#005C55]'">{{ statusLabel(encounter.status) }}</span></div>
                    <p class="mt-2 text-sm text-[#64748B]">{{ encounter.patient.full_name }} · {{ encounter.professional.full_name }} · {{ formatDate(encounter.encounter_date) }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="appUrl(`/encounters/${encounter.id}/clinical-plans/create`)" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ClipboardCheck class="h-4 w-4" /> Crear plan clínico</Link>
                    <button v-if="encounter.status === 'draft'" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#455653]" @click="isEditing = !isEditing"><Pencil class="h-4 w-4" /> {{ isEditing ? 'Cerrar edición' : 'Editar borrador' }}</button>
                    <button v-if="encounter.status === 'draft'" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white" @click="isFinalizeModal = true"><ShieldCheck class="h-4 w-4" /> Finalizar y sellar</button>
                    <button v-else class="inline-flex h-10 items-center gap-2 border border-[#F79009] bg-[#FFFAEB] px-4 text-sm font-semibold text-[#93370D]" @click="isAmendmentModal = true"><FileEdit class="h-4 w-4" /> Registrar enmienda</button>
                </div>
            </header>

            <section v-if="integrity.status === 'verified'" class="flex flex-col gap-3 border border-[#75C7BB] bg-[#F1FAF8] p-4 lg:flex-row lg:items-center lg:justify-between"><div class="flex items-start gap-3"><ShieldCheck class="mt-0.5 h-5 w-5 shrink-0 text-[#007D73]" /><div><h2 class="font-bold text-[#005C55]">Integridad clínica verificada</h2><p class="mt-1 text-xs text-[#455653]">El sello SHA-256 cubre cabecera, signos vitales, evolución SOAP, diagnósticos y prescripciones.</p></div></div><p class="max-w-xl break-all font-mono text-[10px] text-[#52615E]">{{ encounter.integrity_hash }}</p></section>
            <section v-else-if="integrity.status === 'legacy'" class="flex items-start gap-3 border border-[#FEC84B] bg-[#FFFAEB] p-4"><AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-[#B54708]" /><div><h2 class="font-bold text-[#93370D]">Sello heredado válido</h2><p class="mt-1 text-xs text-[#7A2E0E]">Este registro usa el esquema anterior, que protege la cabecera pero no todo el detalle clínico. Sus enmiendas siguen siendo obligatorias.</p></div></section>
            <section v-else-if="integrity.status === 'mismatch'" class="flex items-start gap-3 border border-[#FDA29B] bg-[#FFF5F4] p-4"><ShieldAlert class="mt-0.5 h-5 w-5 shrink-0 text-[#B42318]" /><div><h2 class="font-bold text-[#912018]">La verificación de integridad falló</h2><p class="mt-1 text-xs text-[#912018]">El contenido actual no coincide con el sello almacenado. No utilices este registro para decisiones clínicas hasta completar la revisión de auditoría.</p></div></section>

            <form v-if="isEditing && encounter.status === 'draft'" class="space-y-5 border border-[#7AAFA7] bg-[#F7FAF9] p-5" @submit.prevent="saveDraft">
                <div class="flex items-center justify-between"><div><h2 class="flex items-center gap-2 font-bold text-[#131B2E]"><FileEdit class="h-5 w-5 text-[#005C55]" /> Editar borrador</h2><p class="mt-1 text-xs text-[#64748B]">Los cambios permanecen editables hasta que finalices el registro.</p></div><button type="button" class="text-[#64748B]" @click="isEditing = false"><X class="h-5 w-5" /></button></div>
                <div class="grid gap-4 md:grid-cols-2"><label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Motivo de consulta</span><textarea v-model="editForm.chief_complaint" rows="2" class="w-full border border-[#9AAEAA] bg-white px-3 py-2 text-sm"></textarea></label><label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Examen físico</span><textarea v-model="editForm.physical_examination" rows="3" class="w-full border border-[#9AAEAA] bg-white px-3 py-2 text-sm"></textarea></label></div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Presión arterial</span><input v-model="editForm.vital_signs.blood_pressure" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 font-mono text-sm" placeholder="120/80" /></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Frecuencia cardíaca</span><input v-model="editForm.vital_signs.heart_rate" type="number" min="20" max="250" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 font-mono text-sm" /></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Temperatura</span><input v-model="editForm.vital_signs.temperature" type="number" min="30" max="45" step="0.1" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 font-mono text-sm" /></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Saturación O₂</span><input v-model="editForm.vital_signs.oxygen_saturation" type="number" min="50" max="100" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 font-mono text-sm" /></label></div>
                <div class="grid gap-4 md:grid-cols-2"><label v-for="field in [{ key: 'subjective', letter: 'S', label: 'Subjetivo' }, { key: 'objective', letter: 'O', label: 'Objetivo' }, { key: 'assessment', letter: 'A', label: 'Evaluación' }, { key: 'plan', letter: 'P', label: 'Plan' }]" :key="field.key"><span class="mb-1 flex items-center gap-2 text-xs font-semibold text-[#455653]"><b class="grid h-5 w-5 place-items-center bg-[#D8ECE9] text-[#005C55]">{{ field.letter }}</b>{{ field.label }}</span><textarea v-model="editForm[field.key as 'subjective' | 'objective' | 'assessment' | 'plan']" rows="3" class="w-full border border-[#9AAEAA] bg-white px-3 py-2 text-sm"></textarea></label><label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Tratamiento realizado</span><textarea v-model="editForm.treatment_performed" rows="4" class="w-full border border-[#9AAEAA] bg-white px-3 py-2 text-sm"></textarea></label><label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Recomendaciones</span><textarea v-model="editForm.recommendations" rows="2" class="w-full border border-[#9AAEAA] bg-white px-3 py-2 text-sm"></textarea></label></div>
                <div class="flex justify-end gap-2"><button type="button" class="h-10 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold" @click="isEditing = false">Cancelar</button><button :disabled="editForm.processing" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-5 text-sm font-semibold text-white disabled:opacity-60"><Save class="h-4 w-4" /> Guardar cambios</button></div>
            </form>

            <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
                <div class="space-y-5">
                    <section class="border border-[#BDC9C6] bg-white shadow-sm"><header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><Stethoscope class="h-5 w-5 text-[#005C55]" /><h2 class="font-bold text-[#131B2E]">Contexto clínico</h2></header><div class="grid gap-4 p-5 md:grid-cols-2"><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Motivo de consulta</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#263633]">{{ encounter.chief_complaint || 'No especificado' }}</p></div><div><p class="text-xs font-semibold uppercase tracking-wide text-[#64748B]">Examen físico</p><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#263633]">{{ encounter.physical_examination || 'No especificado' }}</p></div></div></section>
                    <section class="border border-[#BDC9C6] bg-white shadow-sm"><header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><Activity class="h-5 w-5 text-[#005C55]" /><div><h2 class="font-bold text-[#131B2E]">Evolución SOAP</h2><p class="text-xs text-[#64748B]">Razonamiento y conducta clínica documentada.</p></div></header><div v-if="encounter.evolution" class="grid gap-4 p-5 md:grid-cols-2"><article v-for="field in [{ letter: 'S', label: 'Subjetivo', value: encounter.evolution.subjective }, { letter: 'O', label: 'Objetivo', value: encounter.evolution.objective }, { letter: 'A', label: 'Evaluación', value: encounter.evolution.assessment }, { letter: 'P', label: 'Plan', value: encounter.evolution.plan }]" :key="field.letter" class="border-l-2 border-[#7AAFA7] pl-4"><h3 class="flex items-center gap-2 text-xs font-bold text-[#005C55]"><b class="grid h-6 w-6 place-items-center bg-[#D8ECE9]">{{ field.letter }}</b>{{ field.label }}</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#455653]">{{ field.value || 'Sin registro' }}</p></article><article class="border border-[#B7D9D4] bg-[#F1FAF8] p-4 md:col-span-2"><h3 class="font-bold text-[#005C55]">Tratamiento realizado</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#263633]">{{ encounter.evolution.treatment_performed || 'Sin tratamiento documentado.' }}</p></article><article v-if="encounter.evolution.recommendations" class="border border-[#D8E0DE] bg-[#FAFBFB] p-4 md:col-span-2"><h3 class="font-bold text-[#455653]">Recomendaciones</h3><p class="mt-2 whitespace-pre-line text-sm leading-6 text-[#455653]">{{ encounter.evolution.recommendations }}</p></article></div><p v-else class="p-5 text-sm text-[#64748B]">No se registró una evolución estructurada.</p></section>
                    <section v-if="encounter.amendments.length" class="border border-[#FEC84B] bg-white shadow-sm"><header class="flex items-center gap-3 border-b border-[#FEC84B] bg-[#FFFAEB] px-5 py-4"><FileSignature class="h-5 w-5 text-[#B54708]" /><div><h2 class="font-bold text-[#7A2E0E]">Historial de enmiendas</h2><p class="text-xs text-[#93370D]">Las aclaraciones se anexan sin reemplazar el registro original.</p></div></header><div class="divide-y divide-[#FDE68A]"><article v-for="amendment in encounter.amendments" :key="amendment.id" class="p-5"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="text-sm font-bold text-[#7A2E0E]">{{ amendment.reason }}</p><p class="mt-1 text-xs text-[#64748B]">{{ amendment.amended_by.name }} · {{ formatDate(amendment.amended_at) }}</p></div><span class="border border-[#FEC84B] bg-[#FFFAEB] px-2 py-1 text-[10px] font-bold text-[#93370D]">Enmienda firmada</span></div><p class="mt-3 whitespace-pre-line border-l-2 border-[#F79009] pl-3 text-sm leading-6 text-[#455653]">{{ amendmentNote(amendment) }}</p><p class="mt-3 break-all font-mono text-[9px] text-[#98A2B3]">SHA-256 {{ amendment.integrity_hash }}</p></article></div></section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-6">
                    <section class="border border-[#BDC9C6] bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-bold text-[#131B2E]"><HeartPulse class="h-5 w-5 text-[#005C55]" /> Signos vitales</h2><dl class="mt-4 grid grid-cols-2 gap-3"><div v-for="vital in [{ label: 'Presión', value: encounter.vital_signs?.blood_pressure, unit: 'mmHg' }, { label: 'Pulso', value: encounter.vital_signs?.heart_rate, unit: 'lpm' }, { label: 'Temperatura', value: encounter.vital_signs?.temperature, unit: '°C' }, { label: 'Saturación', value: encounter.vital_signs?.oxygen_saturation, unit: '%' }]" :key="vital.label" class="border border-[#D8E0DE] bg-[#F7FAF9] p-3"><dt class="text-[10px] font-semibold uppercase tracking-wide text-[#64748B]">{{ vital.label }}</dt><dd class="mt-1 font-mono text-lg font-bold text-[#131B2E]">{{ vital.value || '—' }} <small v-if="vital.value" class="text-[10px] font-normal text-[#64748B]">{{ vital.unit }}</small></dd></div></dl></section>
                    <section class="border border-[#BDC9C6] bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-bold text-[#131B2E]"><ClipboardCheck class="h-5 w-5 text-[#005C55]" /> Diagnósticos</h2><div v-if="encounter.diagnoses.length" class="mt-4 space-y-2"><article v-for="diagnosis in encounter.diagnoses" :key="diagnosis.id" class="border border-[#D8E0DE] p-3"><p class="text-sm font-semibold text-[#131B2E]"><span class="font-mono text-[#005C55]">{{ diagnosis.code || 'S/C' }}</span> · {{ diagnosis.description }}</p><p class="mt-1 text-[10px] uppercase tracking-wide text-[#64748B]">{{ diagnosis.type === 'definitive' ? 'Definitivo' : 'Presuntivo' }}</p></article></div><p v-else class="mt-4 text-sm text-[#64748B]">Sin diagnósticos.</p></section>
                    <section class="border border-[#BDC9C6] bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-bold text-[#131B2E]"><Pill class="h-5 w-5 text-[#B54708]" /> Prescripciones</h2><div v-if="encounter.prescriptions.length" class="mt-4 space-y-2"><article v-for="prescription in encounter.prescriptions" :key="prescription.id" class="border-l-2 border-[#F79009] bg-[#FFFAEB] p-3"><p class="text-sm font-bold text-[#7A2E0E]">{{ prescription.medication_name }} · {{ prescription.dosage }}</p><p class="mt-1 text-xs text-[#455653]">{{ prescription.frequency }} durante {{ prescription.duration }}</p><p v-if="prescription.instructions" class="mt-1 text-xs italic text-[#64748B]">{{ prescription.instructions }}</p></article></div><p v-else class="mt-4 text-sm text-[#64748B]">Sin prescripciones.</p></section>
                    <section v-if="encounter.finalized_at" class="border border-[#B7D9D4] bg-[#F1FAF8] p-5"><h2 class="flex items-center gap-2 font-bold text-[#005C55]"><LockKeyhole class="h-5 w-5" /> Trazabilidad</h2><dl class="mt-3 space-y-2 text-xs text-[#455653]"><div><dt class="text-[#64748B]">Finalizado por</dt><dd class="font-semibold">{{ encounter.finalized_by?.name || 'Usuario clínico' }}</dd></div><div><dt class="text-[#64748B]">Fecha de sellado</dt><dd class="font-semibold">{{ formatDate(encounter.finalized_at) }}</dd></div><div><dt class="text-[#64748B]">Algoritmo</dt><dd class="font-mono font-semibold">{{ integrity.algorithm }}</dd></div></dl></section>
                </aside>
            </div>
        </div>

        <div v-if="isFinalizeModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-sm" @click.self="isFinalizeModal = false"><section class="w-full max-w-lg border border-[#75C7BB] bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-[#D8E0DE] p-5"><div class="flex gap-3"><div class="grid h-10 w-10 shrink-0 place-items-center bg-[#D8ECE9] text-[#005C55]"><ShieldCheck class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">Finalizar y sellar evolución</h2><p class="mt-1 text-xs text-[#64748B]">Esta acción establece la versión clínica definitiva.</p></div></div><button @click="isFinalizeModal = false"><X class="h-5 w-5" /></button></header><div class="space-y-3 p-5 text-sm text-[#455653]"><p>El sello protegerá el contenido completo mediante SHA-256:</p><ul class="space-y-2"><li class="flex gap-2"><CheckCircle2 class="h-4 w-4 shrink-0 text-[#007D73]" /> Contexto, examen y signos vitales.</li><li class="flex gap-2"><CheckCircle2 class="h-4 w-4 shrink-0 text-[#007D73]" /> Evolución SOAP y tratamiento realizado.</li><li class="flex gap-2"><CheckCircle2 class="h-4 w-4 shrink-0 text-[#007D73]" /> Diagnósticos y prescripciones.</li></ul><p class="border border-[#FEC84B] bg-[#FFFAEB] p-3 text-xs text-[#7A2E0E]">Después del sellado solo podrás añadir enmiendas firmadas; el contenido original no se reemplaza.</p><p v-if="finalizeForm.errors.error" class="text-xs font-semibold text-[#B42318]">{{ finalizeForm.errors.error }}</p></div><footer class="flex justify-end gap-2 border-t border-[#D8E0DE] p-4"><button class="h-10 border border-[#9AAEAA] px-4 text-sm" @click="isFinalizeModal = false">Cancelar</button><button :disabled="finalizeForm.processing" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-60" @click="finalizeEncounter"><ShieldCheck class="h-4 w-4" /> Confirmar sellado</button></footer></section></div>

        <div v-if="isAmendmentModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-sm" @click.self="isAmendmentModal = false"><form class="w-full max-w-xl border border-[#FEC84B] bg-white shadow-2xl" @submit.prevent="submitAmendment"><header class="flex items-start justify-between border-b border-[#D8E0DE] p-5"><div class="flex gap-3"><div class="grid h-10 w-10 shrink-0 place-items-center bg-[#FEF0C7] text-[#B54708]"><FileSignature class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">Registrar enmienda clínica</h2><p class="mt-1 text-xs text-[#64748B]">La aclaración se firma y se anexa sin sobrescribir el original.</p></div></div><button type="button" @click="isAmendmentModal = false"><X class="h-5 w-5" /></button></header><div class="space-y-4 p-5"><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Motivo o justificación *</span><textarea v-model="amendmentForm.reason" required maxlength="1000" rows="3" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Explica por qué es necesaria esta aclaración"></textarea><span v-if="amendmentForm.errors.reason" class="mt-1 block text-xs text-[#B42318]">{{ amendmentForm.errors.reason }}</span></label><label><span class="mb-1 block text-xs font-semibold text-[#455653]">Contenido de la aclaración *</span><textarea v-model="amendmentForm.amended_content.note" required rows="5" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Detalla la información corregida o complementaria"></textarea><span v-if="amendmentForm.errors.amended_content" class="mt-1 block text-xs text-[#B42318]">{{ amendmentForm.errors.amended_content }}</span></label><div class="flex gap-2 border border-[#FEC84B] bg-[#FFFAEB] p-3 text-xs text-[#7A2E0E]"><AlertTriangle class="h-4 w-4 shrink-0" /> Esta enmienda formará parte permanente de la trazabilidad clínica.</div></div><footer class="flex justify-end gap-2 border-t border-[#D8E0DE] p-4"><button type="button" class="h-10 border border-[#9AAEAA] px-4 text-sm" @click="isAmendmentModal = false">Cancelar</button><button :disabled="amendmentForm.processing" class="h-10 bg-[#B54708] px-4 text-sm font-semibold text-white disabled:opacity-60">Firmar enmienda</button></footer></form></div>
    </ClinicLayout>
</template>
