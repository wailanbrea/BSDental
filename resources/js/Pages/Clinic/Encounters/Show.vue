<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { 
    Stethoscope, 
    ArrowLeft, 
    CheckCircle2, 
    FileSignature, 
    Pill, 
    ShieldCheck, 
    AlertTriangle, 
    FileEdit, 
    X 
} from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface ProfessionalDetails {
    id: string
    full_name: string
}

interface DiagnosisItem {
    id: string
    code: string | null
    description: string
    type: string
}

interface EvolutionDetails {
    subjective: string | null
    objective: string | null
    assessment: string | null
    plan: string | null
    treatment_performed: string | null
    recommendations: string | null
}

interface PrescriptionItem {
    id: string
    medication_name: string
    dosage: string
    frequency: string
    duration: string
    instructions: string | null
}

interface AmendmentItem {
    id: string
    reason: string
    amended_content: Record<string, unknown>
    amended_at: string
    integrity_hash: string
    amended_by: {
        name: string
    }
}

interface EncounterDetails {
    id: string
    patient_id: string
    encounter_date: string
    chief_complaint: string | null
    physical_examination: string | null
    status: 'draft' | 'finalized' | 'amended'
    finalized_at: string | null
    integrity_hash: string | null
    patient: PatientDetails
    professional: ProfessionalDetails
    evolution: EvolutionDetails | null
    diagnoses: DiagnosisItem[]
    prescriptions: PrescriptionItem[]
    amendments: AmendmentItem[]
    finalized_by?: {
        name: string
    }
}

const props = defineProps<{
    encounter: EncounterDetails
}>()

const isAmendmentModal = ref(false)

const finalizeForm = useForm({})
const amendmentForm = useForm({
    reason: '',
    amended_content: {
        note: '',
    },
})

function finalizeEncounter() {
    if (confirm('¿Finalizar y sellar inmutablemente este encuentro clínico? Una vez finalizado, las modificaciones requerirán enmiendas firmadas.')) {
        finalizeForm.post(`/encounters/${props.encounter.id}/finalize`)
    }
}

function submitAmendment() {
    amendmentForm.post(`/encounters/${props.encounter.id}/amend`, {
        onSuccess: () => {
            isAmendmentModal.value = false
            amendmentForm.reset()
        },
    })
}
</script>

<template>
    <Head :title="`Atención Clínica #${encounter.id.substring(0, 8)} — ${encounter.patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <Stethoscope class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold text-white tracking-tight">Atención Clínica Odontológica</h1>
                            <span
                                :class="[
                                    encounter.status === 'finalized' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                                    encounter.status === 'amended' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                                    'bg-slate-700 text-slate-300 border-slate-600'
                                ]"
                                class="px-3 py-1 text-xs font-bold rounded-full border"
                            >
                                {{ encounter.status === 'finalized' ? 'Sellada e Inmutable' : encounter.status === 'amended' ? 'Enmendada' : 'Borrador Abierto' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            Paciente: <span class="text-white font-semibold">{{ encounter.patient.full_name }}</span> ({{ encounter.patient.record_number }}) | 
                            Atendido por: <span class="text-slate-300">Dr(a). {{ encounter.professional.full_name }}</span> | 
                            Fecha: <span class="text-teal-400 font-mono">{{ encounter.encounter_date.substring(0, 16) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a :href="`/patients/${encounter.patient_id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" /> Ficha 360
                    </a>

                    <!-- Finalize Button if Draft -->
                    <button
                        v-if="encounter.status === 'draft'"
                        :disabled="finalizeForm.processing"
                        class="flex items-center gap-2 px-4 py-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold rounded-xl shadow-lg shadow-emerald-500/20 text-xs transition"
                        @click="finalizeEncounter"
                    >
                        <ShieldCheck class="w-4 h-4" /> Finalizar & Sellar
                    </button>

                    <!-- Amendment Button if Finalized/Amended -->
                    <button
                        v-if="encounter.status === 'finalized' || encounter.status === 'amended'"
                        class="flex items-center gap-2 px-4 py-2 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 font-bold rounded-xl text-xs transition"
                        @click="isAmendmentModal = true"
                    >
                        <FileEdit class="w-4 h-4" /> Registrar Enmienda
                    </button>
                </div>
            </div>

            <!-- Inmutability / Integrity Stamp Card -->
            <div v-if="encounter.integrity_hash" class="p-4 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl flex items-center justify-between text-xs">
                <div class="flex items-center gap-3">
                    <CheckCircle2 class="w-5 h-5 text-emerald-400 shrink-0" />
                    <div>
                        <div class="text-emerald-300 font-bold">Registro Clínico Sellado & Protegido contra Alteraciones</div>
                        <div class="text-slate-400 font-mono text-[11px]">SHA256: {{ encounter.integrity_hash }}</div>
                    </div>
                </div>
                <div class="text-right text-slate-400 text-[11px]">
                    Sellado por: {{ encounter.finalized_by?.name || 'Profesional' }} <br />
                    {{ encounter.finalized_at }}
                </div>
            </div>

            <!-- SOAP Evolution & Treatment Details -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-6">
                <div>
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider mb-2">Motivo de Consulta & Examen Físico</h2>
                    <p class="text-sm text-slate-200 bg-slate-900/60 p-4 rounded-xl border border-slate-700/40">
                        {{ encounter.chief_complaint || 'No especificado' }}
                    </p>
                </div>

                <div v-if="encounter.evolution" class="space-y-4">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Evolución Clínica & Procedimiento</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-900/60 rounded-xl border border-slate-700/40 text-xs">
                            <span class="text-teal-400 font-bold block mb-1">Subjetivo (S):</span>
                            {{ encounter.evolution.subjective || '—' }}
                        </div>
                        <div class="p-4 bg-slate-900/60 rounded-xl border border-slate-700/40 text-xs">
                            <span class="text-teal-400 font-bold block mb-1">Objetivo (O):</span>
                            {{ encounter.evolution.objective || '—' }}
                        </div>
                    </div>

                    <div class="p-4 bg-slate-900/80 rounded-xl border border-teal-500/20 text-xs space-y-2">
                        <span class="text-teal-400 font-bold block text-sm">Tratamiento Realizado en la Sesión:</span>
                        <p class="text-slate-100 text-sm leading-relaxed whitespace-pre-line">{{ encounter.evolution.treatment_performed || 'Sin detalles de tratamiento.' }}</p>
                    </div>
                </div>

                <!-- Diagnósticos -->
                <div v-if="encounter.diagnoses && encounter.diagnoses.length > 0">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider mb-2">Diagnósticos Asignados</h2>
                    <div class="space-y-1.5">
                        <div v-for="d in encounter.diagnoses" :key="d.id" class="p-3 bg-slate-900/60 rounded-xl border border-slate-700/40 flex items-center justify-between text-xs">
                            <div>
                                <span class="font-mono font-bold text-teal-400">{{ d.code || 'S/C' }}</span> — 
                                <span class="text-white">{{ d.description }}</span>
                            </div>
                            <span class="text-slate-500">{{ d.type === 'definitive' ? 'Definitivo' : 'Presuntivo' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Prescripciones -->
                <div v-if="encounter.prescriptions && encounter.prescriptions.length > 0">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider mb-2 flex items-center gap-2">
                        <Pill class="w-4 h-4" /> Receta Farmacológica
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div v-for="rx in encounter.prescriptions" :key="rx.id" class="p-4 bg-slate-900/80 rounded-xl border border-teal-500/30 space-y-1 text-xs">
                            <div class="font-bold text-white text-sm text-teal-300">{{ rx.medication_name }} {{ rx.dosage }}</div>
                            <div class="text-slate-400">{{ rx.frequency }} por {{ rx.duration }}</div>
                            <div v-if="rx.instructions" class="text-slate-500 italic text-[11px]">{{ rx.instructions }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Amendment Timeline History -->
            <div v-if="encounter.amendments && encounter.amendments.length > 0" class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4">
                <h2 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-2">
                    <FileSignature class="w-4 h-4" /> Historial de Enmiendas Clínicas (Inmutable)
                </h2>

                <div class="space-y-3">
                    <div v-for="am in encounter.amendments" :key="am.id" class="p-4 bg-slate-900/90 border border-amber-500/30 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-center justify-between text-amber-300 font-bold">
                            <span>Enmienda Médica — {{ am.amended_at }}</span>
                            <span class="text-slate-400 font-normal">Firmado por: {{ am.amended_by.name }}</span>
                        </div>
                        <p class="text-slate-200"><span class="text-slate-500">Justificación:</span> {{ am.reason }}</p>
                        <div class="text-[11px] font-mono text-slate-500">Hash de Enmienda: {{ am.integrity_hash }}</div>
                    </div>
                </div>
            </div>

            <!-- Amendment Modal -->
            <div v-if="isAmendmentModal" class="p-6 bg-slate-800 border border-amber-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <AlertTriangle class="w-5 h-5 text-amber-400" /> Registrar Enmienda Médica
                    </h2>
                    <button class="text-slate-400 hover:text-white" @click="isAmendmentModal = false"><X class="w-5 h-5" /></button>
                </div>

                <p class="text-xs text-slate-400">
                    Las enmiendas quedan registradas con firma digital y no sobreescriben la ficha original.
                </p>

                <form class="space-y-4" @submit.prevent="submitAmendment">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Motivo / Justificación Clínica de la Corrección *</label>
                        <textarea v-model="amendmentForm.reason" rows="3" required placeholder="Explique detalladamente el motivo de la enmienda..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Contenido / Aclaración Clínica *</label>
                        <textarea v-model="amendmentForm.amended_content.note" rows="3" required placeholder="Detalle la información corregida o complementada..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isAmendmentModal = false">Cancelar</button>
                        <button type="submit" :disabled="amendmentForm.processing" class="px-4 py-2 bg-amber-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-amber-400">Firmar Enmienda</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>