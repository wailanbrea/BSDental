<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Stethoscope, ArrowLeft, Plus, Trash2, AlertTriangle } from 'lucide-vue-next'

interface MedicalHistory {
    allergies: string[] | null
    systemic_conditions: string[] | null
}

interface Patient {
    id: string
    record_number: string
    full_name: string
    medical_history: MedicalHistory | null
}

interface Professional {
    id: string
    full_name: string
}

const props = defineProps<{
    patient: Patient
    professionals: Professional[]
    appointmentId?: string
}>()

const form = useForm({
    patient_id: props.patient.id,
    professional_id: props.professionals[0]?.id || '',
    appointment_id: props.appointmentId || null,
    encounter_date: new Date().toISOString().slice(0, 16),
    chief_complaint: '',
    physical_examination: '',
    vital_signs: {
        blood_pressure: '',
        heart_rate: '',
        temperature: '',
    },
    // SOAP
    subjective: '',
    objective: '',
    assessment: '',
    plan: '',
    treatment_performed: '',
    recommendations: '',
    // Diagnoses
    diagnoses: [] as { code: string; description: string; type: string }[],
    // Prescriptions
    prescriptions: [] as { medication_name: string; dosage: string; frequency: string; duration: string; instructions: string }[],
})

const newDiagnosis = ref({ code: '', description: '', type: 'definitive' })
const newPrescription = ref({ medication_name: '', dosage: '', frequency: '', duration: '', instructions: '' })

function addDiagnosis() {
    if (newDiagnosis.value.description.trim()) {
        form.diagnoses.push({ ...newDiagnosis.value })
        newDiagnosis.value = { code: '', description: '', type: 'definitive' }
    }
}

function removeDiagnosis(index: number) {
    form.diagnoses.splice(index, 1)
}

function addPrescription() {
    if (newPrescription.value.medication_name.trim()) {
        form.prescriptions.push({ ...newPrescription.value })
        newPrescription.value = { medication_name: '', dosage: '', frequency: '', duration: '', instructions: '' }
    }
}

function removePrescription(index: number) {
    form.prescriptions.splice(index, 1)
}

function submit() {
    form.post('/encounters')
}
</script>

<template>
    <Head :title="`Nueva Atención Clínica — ${patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <Stethoscope class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Nueva Consulta / Encuentro Clínico</h1>
                        <p class="text-sm text-slate-400">
                            Paciente: <span class="text-white font-semibold">{{ patient.full_name }}</span> 
                            <span class="font-mono text-teal-400 ml-2">({{ patient.record_number }})</span>
                        </p>
                    </div>
                </div>

                <a :href="`/patients/${patient.id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                    <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                </a>
            </div>

            <!-- Medical Alerts Warning Banner -->
            <div v-if="patient.medical_history?.allergies && patient.medical_history.allergies.length > 0" class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex items-center gap-3 text-amber-300 text-xs">
                <AlertTriangle class="w-5 h-5 text-amber-400 shrink-0" />
                <div>
                    <span class="font-bold">¡ALERTA CLÍNICA DEL PACIENTE!:</span> Alergias a {{ patient.medical_history.allergies.join(', ') }}
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Seccion 1: Datos Generales y Signos Vitales -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white text-teal-400">1. Datos de la Consulta & Examen Físico</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Odontólogo / Profesional Tratante *</label>
                            <select v-model="form.professional_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                                <option v-for="p in props.professionals" :key="p.id" :value="p.id">Dr(a). {{ p.full_name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Fecha y Hora del Encuentro</label>
                            <input v-model="form.encounter_date" type="datetime-local" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Motivo de Consulta (Chief Complaint)</label>
                            <textarea v-model="form.chief_complaint" rows="2" placeholder="Ej. Dolor agudo en pieza 46 al masticar..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Examen Clínico Intraoral / Extraoral</label>
                            <textarea v-model="form.physical_examination" rows="2" placeholder="Ej. Cavidad profunda oclusal con dolor a la percusión..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Seccion 2: Evolución SOAP & Tratamiento Realizado -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white text-teal-400">2. Evolución SOAP & Tratamiento Realizado</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Subjetivo (S)</label>
                            <textarea v-model="form.subjective" rows="2" placeholder="Lo que refiere el paciente..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Objetivo (O)</label>
                            <textarea v-model="form.objective" rows="2" placeholder="Hallazgos clínicos observados..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Evaluación / Diagnóstico Clínico (A)</label>
                            <textarea v-model="form.assessment" rows="2" placeholder="Juicio diagnóstico..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Plan de Tratamiento (P)</label>
                            <textarea v-model="form.plan" rows="2" placeholder="Pasos y procedimientos a seguir..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tratamiento Realizado en la Sesión *</label>
                            <textarea v-model="form.treatment_performed" rows="3" placeholder="Ej. Apertura cameral pieza 46, instrumentación y medicación intraconducto con hidróxido de calcio..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Seccion 3: Diagnósticos Estructurados -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white text-teal-400">3. Diagnósticos Clínicos (CIE-10 / Odontológicos)</h2>

                    <div class="flex flex-col md:flex-row gap-3">
                        <input v-model="newDiagnosis.code" type="text" placeholder="Código (ej. K02.1)" class="w-32 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        <input v-model="newDiagnosis.description" type="text" placeholder="Descripción del diagnóstico (ej. Pulpitis irreversible)" class="flex-1 px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        <select v-model="newDiagnosis.type" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option value="definitive">Definitivo</option>
                            <option value="presumptive">Presuntivo</option>
                        </select>
                        <button type="button" class="px-4 py-2 bg-teal-500/20 text-teal-300 text-xs font-bold rounded-lg border border-teal-500/30 flex items-center gap-1" @click="addDiagnosis">
                            <Plus class="w-3.5 h-3.5" /> Agregar
                        </button>
                    </div>

                    <div v-if="form.diagnoses.length > 0" class="space-y-2 pt-2">
                        <div v-for="(diag, idx) in form.diagnoses" :key="idx" class="p-3 bg-slate-900/80 border border-slate-700/50 rounded-xl flex items-center justify-between">
                            <div class="text-xs">
                                <span class="font-mono font-bold text-teal-400">{{ diag.code || 'S/C' }}</span> — 
                                <span class="text-white">{{ diag.description }}</span>
                                <span class="text-slate-500 ml-2">({{ diag.type === 'definitive' ? 'Definitivo' : 'Presuntivo' }})</span>
                            </div>
                            <button type="button" class="text-rose-400 hover:text-rose-300" @click="removeDiagnosis(idx)"><Trash2 class="w-4 h-4" /></button>
                        </div>
                    </div>
                </div>

                <!-- Seccion 4: Receta / Prescripción Médica -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white text-teal-400">4. Receta & Prescripciones Farmacológicas</h2>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                        <input v-model="newPrescription.medication_name" type="text" placeholder="Medicamento (ej. Ibuprofeno)" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        <input v-model="newPrescription.dosage" type="text" placeholder="Dosis (ej. 600 mg)" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        <input v-model="newPrescription.frequency" type="text" placeholder="Frecuencia (ej. c/8h)" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        <div class="flex gap-2">
                            <input v-model="newPrescription.duration" type="text" placeholder="Duración (ej. 3 días)" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                            <button type="button" class="px-4 py-2 bg-teal-500/20 text-teal-300 text-xs font-bold rounded-lg border border-teal-500/30 flex items-center gap-1" @click="addPrescription">
                                <Plus class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <div v-if="form.prescriptions.length > 0" class="space-y-2 pt-2">
                        <div v-for="(rx, idx) in form.prescriptions" :key="idx" class="p-3 bg-slate-900/80 border border-slate-700/50 rounded-xl flex items-center justify-between">
                            <div class="text-xs">
                                <span class="font-bold text-teal-400">{{ rx.medication_name }}</span> {{ rx.dosage }} — {{ rx.frequency }} por {{ rx.duration }}
                            </div>
                            <button type="button" class="text-rose-400 hover:text-rose-300" @click="removePrescription(idx)"><Trash2 class="w-4 h-4" /></button>
                        </div>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end gap-3 pt-4">
                    <a :href="`/patients/${patient.id}`" class="px-6 py-2.5 bg-slate-800 text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-700 transition">Cancelar</a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition disabled:opacity-50"
                    >
                        Guardar Borrador de Atención
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>