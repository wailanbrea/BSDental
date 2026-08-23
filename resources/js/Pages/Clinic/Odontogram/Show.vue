<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { ArrowLeft, Clock, History, FileText } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface OdontogramDetails {
    id: string
    patient_id: string
    type: string
}

interface ConditionSummary {
    id: string
    condition: string
    surface: string
    lifecycle_state: string
    recorded_at: string
    notes: string | null
}

interface ToothData {
    tooth_number: number
    conditions: ConditionSummary[]
    surfaces: Record<string, { condition: string; lifecycle_state: string }>
    latest_state: string
}

interface EntryLog {
    id: string
    tooth_number: number
    surface: string
    condition: string
    lifecycle_state: string
    notes: string | null
    recorded_at: string
    recorded_by?: {
        name: string
    }
}

const props = defineProps<{
    patient: PatientDetails
    odontogram: OdontogramDetails
    matrix: Record<number, ToothData>
    entries: EntryLog[]
}>()

// Quadrants definition (FDI standard)
const adultUpper = [
    [18, 17, 16, 15, 14, 13, 12, 11], // Q1
    [21, 22, 23, 24, 25, 26, 27, 28], // Q2
]

const adultLower = [
    [48, 47, 46, 45, 44, 43, 42, 41], // Q4
    [31, 32, 33, 34, 35, 36, 37, 38], // Q3
]

const selectedTooth = ref<number>(16)
const isRecordModal = ref(false)

const form = useForm({
    tooth_number: 16,
    surface: 'all',
    condition: 'caries',
    lifecycle_state: 'initial_diagnosis',
    notes: '',
})

function openToothModal(tooth: number) {
    selectedTooth.value = tooth
    form.tooth_number = tooth
    isRecordModal.value = true
}

function submitEntry() {
    form.post(`/patients/${props.patient.id}/odontogram/entries`, {
        onSuccess: () => {
            isRecordModal.value = false
            form.reset('notes')
        },
    })
}

function getConditionColor(condition?: string) {
    switch (condition) {
        case 'caries': return 'bg-rose-500 text-white'
        case 'restored_composite': return 'bg-sky-500 text-white'
        case 'restored_amalgam': return 'bg-slate-400 text-slate-950'
        case 'crown': return 'bg-amber-400 text-slate-950'
        case 'endodontic': return 'bg-purple-500 text-white'
        case 'missing': return 'bg-slate-800 text-slate-500 border border-dashed border-slate-600'
        case 'implant': return 'bg-emerald-500 text-white'
        case 'sealant': return 'bg-teal-400 text-slate-950'
        case 'healthy': return 'bg-slate-700 text-slate-300'
        default: return 'bg-slate-800 text-slate-400'
    }
}
</script>

<template>
    <Head :title="`Odontograma — ${patient.full_name} (${patient.record_number})`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <FileText class="w-6 h-6 text-teal-400" /> Odontograma Anatómico FDI
                    </h1>
                    <p class="text-sm text-slate-400">
                        Paciente: <span class="text-white font-semibold">{{ patient.full_name }}</span> ({{ patient.record_number }})
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a :href="`/patients/${patient.id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                    </a>
                </div>
            </div>

            <!-- Legend Bar -->
            <div class="p-4 bg-slate-800/80 border border-slate-700/60 rounded-2xl flex flex-wrap items-center gap-3 text-xs shadow-lg">
                <span class="font-bold text-slate-400 uppercase tracking-wider">Convenciones:</span>
                <span class="px-2.5 py-1 rounded bg-rose-500 text-white font-bold">Caries</span>
                <span class="px-2.5 py-1 rounded bg-sky-500 text-white font-bold">Resina / Obturación</span>
                <span class="px-2.5 py-1 rounded bg-amber-400 text-slate-950 font-bold">Corona</span>
                <span class="px-2.5 py-1 rounded bg-purple-500 text-white font-bold">Endodoncia</span>
                <span class="px-2.5 py-1 rounded bg-emerald-500 text-white font-bold">Implante</span>
                <span class="px-2.5 py-1 rounded bg-slate-800 border border-dashed border-slate-600 text-slate-400 font-bold">Ausente</span>
            </div>

            <!-- Interactive Odontogram Grid -->
            <div class="p-8 bg-slate-800/90 border border-slate-700/60 rounded-3xl space-y-8 shadow-2xl">
                <!-- Maxilar Superior (Q1 & Q2) -->
                <div>
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider mb-4 text-center">Maxilar Superior</h2>
                    <div class="flex justify-center gap-2 overflow-x-auto pb-2">
                        <!-- Q1 -->
                        <div class="flex gap-1.5">
                            <button
                                v-for="tooth in adultUpper[0]"
                                :key="tooth"
                                :class="[getConditionColor(matrix[tooth]?.conditions[matrix[tooth]?.conditions.length - 1]?.condition)]"
                                class="w-12 h-16 rounded-xl border border-slate-700/60 flex flex-col items-center justify-between p-1.5 hover:scale-105 transition shadow"
                                @click="openToothModal(tooth)"
                            >
                                <span class="text-[10px] font-mono font-bold">{{ tooth }}</span>
                                <div class="w-6 h-6 rounded-full border border-white/20 bg-slate-900/40"></div>
                            </button>
                        </div>
                        <div class="w-1 bg-teal-500/30 rounded-full mx-2"></div>
                        <!-- Q2 -->
                        <div class="flex gap-1.5">
                            <button
                                v-for="tooth in adultUpper[1]"
                                :key="tooth"
                                :class="[getConditionColor(matrix[tooth]?.conditions[matrix[tooth]?.conditions.length - 1]?.condition)]"
                                class="w-12 h-16 rounded-xl border border-slate-700/60 flex flex-col items-center justify-between p-1.5 hover:scale-105 transition shadow"
                                @click="openToothModal(tooth)"
                            >
                                <span class="text-[10px] font-mono font-bold">{{ tooth }}</span>
                                <div class="w-6 h-6 rounded-full border border-white/20 bg-slate-900/40"></div>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Maxilar Inferior (Q4 & Q3) -->
                <div>
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider mb-4 text-center">Maxilar Inferior (Mandíbula)</h2>
                    <div class="flex justify-center gap-2 overflow-x-auto pb-2">
                        <!-- Q4 -->
                        <div class="flex gap-1.5">
                            <button
                                v-for="tooth in adultLower[0]"
                                :key="tooth"
                                :class="[getConditionColor(matrix[tooth]?.conditions[matrix[tooth]?.conditions.length - 1]?.condition)]"
                                class="w-12 h-16 rounded-xl border border-slate-700/60 flex flex-col items-center justify-between p-1.5 hover:scale-105 transition shadow"
                                @click="openToothModal(tooth)"
                            >
                                <div class="w-6 h-6 rounded-full border border-white/20 bg-slate-900/40"></div>
                                <span class="text-[10px] font-mono font-bold">{{ tooth }}</span>
                            </button>
                        </div>
                        <div class="w-1 bg-teal-500/30 rounded-full mx-2"></div>
                        <!-- Q3 -->
                        <div class="flex gap-1.5">
                            <button
                                v-for="tooth in adultLower[1]"
                                :key="tooth"
                                :class="[getConditionColor(matrix[tooth]?.conditions[matrix[tooth]?.conditions.length - 1]?.condition)]"
                                class="w-12 h-16 rounded-xl border border-slate-700/60 flex flex-col items-center justify-between p-1.5 hover:scale-105 transition shadow"
                                @click="openToothModal(tooth)"
                            >
                                <div class="w-6 h-6 rounded-full border border-white/20 bg-slate-900/40"></div>
                                <span class="text-[10px] font-mono font-bold">{{ tooth }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historical Odontogram Timeline Logs -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4">
                <h2 class="text-sm font-bold text-white uppercase tracking-wider text-teal-400 flex items-center gap-2">
                    <History class="w-5 h-5" /> Historial de Evolución Dental
                </h2>

                <div v-if="entries.length === 0" class="text-xs text-slate-500 py-6 text-center">
                    No se han registrado hallazgos o tratamientos aún.
                </div>

                <div class="space-y-2">
                    <div v-for="entry in entries" :key="entry.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-xl flex items-center justify-between text-xs">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-400 font-mono font-bold flex items-center justify-center border border-teal-500/20">
                                {{ entry.tooth_number }}
                            </span>
                            <div>
                                <div class="text-white font-bold">
                                    Pieza {{ entry.tooth_number }} — 
                                    <span class="text-teal-300 uppercase">{{ entry.condition }}</span> 
                                    <span class="text-slate-500">({{ entry.surface }})</span>
                                </div>
                                <p v-if="entry.notes" class="text-slate-400 mt-0.5">{{ entry.notes }}</p>
                            </div>
                        </div>

                        <div class="text-right text-slate-400 text-[11px]">
                            <span
:class="[
                                entry.lifecycle_state === 'completed' ? 'text-emerald-400 font-bold' :
                                entry.lifecycle_state === 'planned' ? 'text-sky-400' : 'text-amber-400'
                            ]"
>
                                {{ entry.lifecycle_state === 'completed' ? 'Realizado' : entry.lifecycle_state === 'planned' ? 'Planificado' : 'Diagnóstico Inicial' }}
                            </span>
                            <div class="flex items-center gap-1 mt-0.5"><Clock class="w-3 h-3" /> {{ entry.recorded_at }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for Tooth Recording -->
            <div v-if="isRecordModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Hallazgo en Pieza {{ selectedTooth }}</h2>
                    <button class="text-slate-400 hover:text-white" @click="isRecordModal = false">×</button>
                </div>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitEntry">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Superficie</label>
                        <select v-model="form.surface" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option value="all">Toda la pieza (General)</option>
                            <option value="occlusal_incisal">Oclusal / Incisal</option>
                            <option value="vestibular">Vestibular</option>
                            <option value="lingual_palatal">Lingual / Palatino</option>
                            <option value="mesial">Mesial</option>
                            <option value="distal">Distal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Condición / Diagnóstico</label>
                        <select v-model="form.condition" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option value="caries">Caries Dental</option>
                            <option value="restored_composite">Obturación Resina</option>
                            <option value="restored_amalgam">Obturación Amalgama</option>
                            <option value="crown">Corona Protésica</option>
                            <option value="endodontic">Tratamiento de Conducto</option>
                            <option value="missing">Diente Ausente</option>
                            <option value="implant">Implante Dental</option>
                            <option value="sealant">Sellante Fosas y Fisuras</option>
                            <option value="fracture">Fractura Dental</option>
                            <option value="healthy">Sano / Sin Patología</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Estado de Ejecución</label>
                        <select v-model="form.lifecycle_state" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option value="initial_diagnosis">Diagnóstico Inicial (Hallazgo)</option>
                            <option value="planned">Planificado (Presupuesto)</option>
                            <option value="completed">Realizado / Ejecutado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Notas / Observaciones</label>
                        <input v-model="form.notes" type="text" placeholder="Ej. Grado 2 profundidad" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isRecordModal = false">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>