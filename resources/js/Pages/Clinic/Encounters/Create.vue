<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Activity, AlertTriangle, ArrowLeft, CheckCircle2, ClipboardPlus, HeartPulse, Pill, Plus, Save, Stethoscope, Trash2, UserRound } from 'lucide-vue-next'

interface MedicalHistory {
    allergies: string[] | null
    systemic_conditions: string[] | null
    current_medications?: string[] | null
    is_pregnant?: boolean
    has_pacemaker?: boolean
    bleeding_disorders?: boolean
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

function localDateTime(date = new Date()) {
    const offset = date.getTimezoneOffset() * 60_000
    return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

const form = useForm({
    patient_id: props.patient.id,
    professional_id: props.professionals[0]?.id || '',
    appointment_id: props.appointmentId || null,
    encounter_date: localDateTime(),
    chief_complaint: '',
    physical_examination: '',
    vital_signs: {
        blood_pressure: '',
        heart_rate: '',
        temperature: '',
        oxygen_saturation: '',
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
const clinicalAlerts = computed(() => [
    ...(props.patient.medical_history?.allergies || []).map((value) => ({ label: `Alergia: ${value}`, critical: true })),
    ...(props.patient.medical_history?.systemic_conditions || []).map((value) => ({ label: value, critical: false })),
    ...(props.patient.medical_history?.bleeding_disorders ? [{ label: 'Trastorno de coagulación', critical: true }] : []),
    ...(props.patient.medical_history?.has_pacemaker ? [{ label: 'Paciente con marcapasos', critical: true }] : []),
    ...(props.patient.medical_history?.is_pregnant ? [{ label: 'Embarazo registrado', critical: false }] : []),
])
const canAddPrescription = computed(() => Boolean(
    newPrescription.value.medication_name.trim()
    && newPrescription.value.dosage.trim()
    && newPrescription.value.frequency.trim()
    && newPrescription.value.duration.trim(),
))

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
    if (canAddPrescription.value) {
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
    <ClinicLayout>
        <div class="mx-auto max-w-[1500px] space-y-5">
            <header class="flex flex-col gap-4 border-b border-[#D8E0DE] pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <Link :href="`/patients/${patient.id}`" class="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-[#006B63]"><ArrowLeft class="h-4 w-4" /> Volver a Ficha 360</Link>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#64748B]">Historia clínica</p>
                    <h1 class="mt-1 text-2xl font-bold text-[#131B2E]">Nueva evolución odontológica</h1>
                    <p class="mt-1 text-sm text-[#64748B]">Documenta la atención como borrador antes de revisarla y sellarla.</p>
                </div>
                <div class="flex items-center gap-3 border border-[#BDC9C6] bg-white px-4 py-3">
                    <div class="grid h-10 w-10 place-items-center rounded-full bg-[#D8ECE9] font-bold text-[#005C55]">{{ patient.full_name.charAt(0) }}</div>
                    <div><p class="text-sm font-semibold text-[#131B2E]">{{ patient.full_name }}</p><p class="font-mono text-xs text-[#64748B]">{{ patient.record_number }}</p></div>
                </div>
            </header>

            <div v-if="clinicalAlerts.length" class="border border-[#FDA29B] bg-[#FFF5F4] p-4">
                <div class="flex items-start gap-3"><AlertTriangle class="mt-0.5 h-5 w-5 shrink-0 text-[#B42318]" /><div><h2 class="font-bold text-[#912018]">Alertas clínicas antes de tratar</h2><div class="mt-2 flex flex-wrap gap-2"><span v-for="alert in clinicalAlerts" :key="alert.label" class="border px-2.5 py-1 text-xs font-semibold" :class="alert.critical ? 'border-[#FDA29B] bg-white text-[#B42318]' : 'border-[#FEC84B] bg-[#FFFAEB] text-[#93370D]'">{{ alert.label }}</span></div></div></div>
            </div>

            <form class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]" @submit.prevent="submit">
                <div class="space-y-5">
                    <section class="border border-[#BDC9C6] bg-white shadow-sm">
                        <header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><div class="grid h-9 w-9 place-items-center bg-[#D8ECE9] text-[#005C55]"><Stethoscope class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">1. Contexto de la consulta</h2><p class="text-xs text-[#64748B]">Responsable, fecha, motivo y examen clínico.</p></div></header>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Profesional tratante *</span><select v-model="form.professional_id" required class="h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm"><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select><span v-if="form.errors.professional_id" class="mt-1 block text-xs text-[#B42318]">{{ form.errors.professional_id }}</span></label>
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Fecha y hora *</span><input v-model="form.encounter_date" type="datetime-local" required class="h-10 w-full border border-[#9AAEAA] px-3 text-sm" /><span v-if="form.errors.encounter_date" class="mt-1 block text-xs text-[#B42318]">{{ form.errors.encounter_date }}</span></label>
                            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Motivo de consulta</span><textarea v-model="form.chief_complaint" rows="2" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Ej. Dolor agudo en pieza 46 al masticar"></textarea></label>
                            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Examen intraoral / extraoral</span><textarea v-model="form.physical_examination" rows="3" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Hallazgos clínicos, tejidos blandos, oclusión y pruebas realizadas"></textarea></label>
                        </div>
                    </section>

                    <section class="border border-[#BDC9C6] bg-white shadow-sm">
                        <header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><div class="grid h-9 w-9 place-items-center bg-[#DDE7FF] text-[#2458C6]"><HeartPulse class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">2. Signos vitales</h2><p class="text-xs text-[#64748B]">Mediciones previas al procedimiento.</p></div></header>
                        <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Presión arterial</span><div class="flex"><input v-model="form.vital_signs.blood_pressure" class="h-10 min-w-0 flex-1 border border-r-0 border-[#9AAEAA] px-3 font-mono text-sm" placeholder="120/80" /><span class="grid h-10 place-items-center border border-[#9AAEAA] bg-[#F7FAF9] px-2 text-xs text-[#64748B]">mmHg</span></div></label>
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Frecuencia cardíaca</span><div class="flex"><input v-model="form.vital_signs.heart_rate" type="number" min="20" max="250" class="h-10 min-w-0 flex-1 border border-r-0 border-[#9AAEAA] px-3 font-mono text-sm" placeholder="72" /><span class="grid h-10 place-items-center border border-[#9AAEAA] bg-[#F7FAF9] px-2 text-xs text-[#64748B]">lpm</span></div></label>
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Temperatura</span><div class="flex"><input v-model="form.vital_signs.temperature" type="number" min="30" max="45" step="0.1" class="h-10 min-w-0 flex-1 border border-r-0 border-[#9AAEAA] px-3 font-mono text-sm" placeholder="36.5" /><span class="grid h-10 place-items-center border border-[#9AAEAA] bg-[#F7FAF9] px-2 text-xs text-[#64748B]">°C</span></div></label>
                            <label><span class="mb-1 block text-xs font-semibold text-[#455653]">Saturación O₂</span><div class="flex"><input v-model="form.vital_signs.oxygen_saturation" type="number" min="50" max="100" class="h-10 min-w-0 flex-1 border border-r-0 border-[#9AAEAA] px-3 font-mono text-sm" placeholder="98" /><span class="grid h-10 place-items-center border border-[#9AAEAA] bg-[#F7FAF9] px-2 text-xs text-[#64748B]">%</span></div></label>
                        </div>
                    </section>

                    <section class="border border-[#BDC9C6] bg-white shadow-sm">
                        <header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><div class="grid h-9 w-9 place-items-center bg-[#D8ECE9] text-[#005C55]"><Activity class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">3. Evolución SOAP</h2><p class="text-xs text-[#64748B]">Registro estructurado del razonamiento clínico.</p></div></header>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <label v-for="field in [{ key: 'subjective', letter: 'S', label: 'Subjetivo', hint: 'Lo que refiere el paciente' }, { key: 'objective', letter: 'O', label: 'Objetivo', hint: 'Hallazgos observables y pruebas' }, { key: 'assessment', letter: 'A', label: 'Evaluación', hint: 'Juicio diagnóstico' }, { key: 'plan', letter: 'P', label: 'Plan', hint: 'Conducta y próximos pasos' }]" :key="field.key"><span class="mb-1 flex items-center gap-2 text-xs font-semibold text-[#455653]"><b class="grid h-5 w-5 place-items-center bg-[#D8ECE9] text-[#005C55]">{{ field.letter }}</b>{{ field.label }}</span><textarea v-model="form[field.key as 'subjective' | 'objective' | 'assessment' | 'plan']" rows="3" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" :placeholder="field.hint"></textarea></label>
                            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Tratamiento realizado</span><textarea v-model="form.treatment_performed" rows="4" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Procedimiento, anestesia, técnica, materiales y respuesta del paciente"></textarea></label>
                            <label class="md:col-span-2"><span class="mb-1 block text-xs font-semibold text-[#455653]">Recomendaciones y cuidados posteriores</span><textarea v-model="form.recommendations" rows="2" class="w-full border border-[#9AAEAA] px-3 py-2 text-sm" placeholder="Indicaciones postoperatorias y signos de alarma"></textarea></label>
                        </div>
                    </section>

                    <section class="border border-[#BDC9C6] bg-white shadow-sm">
                        <header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><div class="grid h-9 w-9 place-items-center bg-[#EEEAFF] text-[#5925DC]"><ClipboardPlus class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">4. Diagnósticos estructurados</h2><p class="text-xs text-[#64748B]">CIE-10 o diagnóstico odontológico.</p></div></header>
                        <div class="space-y-4 p-5"><div class="grid gap-3 md:grid-cols-[140px_1fr_150px_auto]"><input v-model="newDiagnosis.code" class="h-10 border border-[#9AAEAA] px-3 font-mono text-sm" placeholder="K02.1" aria-label="Código diagnóstico" /><input v-model="newDiagnosis.description" class="h-10 border border-[#9AAEAA] px-3 text-sm" placeholder="Descripción del diagnóstico" aria-label="Descripción del diagnóstico" /><select v-model="newDiagnosis.type" class="h-10 border border-[#9AAEAA] bg-white px-3 text-sm" aria-label="Tipo de diagnóstico"><option value="definitive">Definitivo</option><option value="presumptive">Presuntivo</option></select><button type="button" class="inline-flex h-10 items-center justify-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="!newDiagnosis.description.trim()" @click="addDiagnosis"><Plus class="h-4 w-4" /> Agregar</button></div><div v-if="form.diagnoses.length" class="divide-y divide-[#D8E0DE] border border-[#D8E0DE]"><div v-for="(diagnosis, index) in form.diagnoses" :key="`${diagnosis.code}-${index}`" class="flex items-center justify-between gap-3 p-3"><p class="text-sm"><span class="font-mono font-bold text-[#005C55]">{{ diagnosis.code || 'S/C' }}</span> · <span class="font-semibold text-[#131B2E]">{{ diagnosis.description }}</span> <span class="text-xs text-[#64748B]">({{ diagnosis.type === 'definitive' ? 'Definitivo' : 'Presuntivo' }})</span></p><button type="button" class="text-[#B42318]" aria-label="Eliminar diagnóstico" @click="removeDiagnosis(index)"><Trash2 class="h-4 w-4" /></button></div></div><p v-else class="text-sm text-[#64748B]">Sin diagnósticos agregados.</p></div>
                    </section>

                    <section class="border border-[#BDC9C6] bg-white shadow-sm">
                        <header class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-5 py-4"><div class="grid h-9 w-9 place-items-center bg-[#FEF0C7] text-[#B54708]"><Pill class="h-5 w-5" /></div><div><h2 class="font-bold text-[#131B2E]">5. Prescripción farmacológica</h2><p class="text-xs text-[#64748B]">Completa medicamento, dosis, frecuencia y duración.</p></div></header>
                        <div class="space-y-4 p-5"><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"><input v-model="newPrescription.medication_name" class="h-10 border border-[#9AAEAA] px-3 text-sm" placeholder="Medicamento" aria-label="Medicamento" /><input v-model="newPrescription.dosage" class="h-10 border border-[#9AAEAA] px-3 text-sm" placeholder="Dosis, ej. 500 mg" aria-label="Dosis" /><input v-model="newPrescription.frequency" class="h-10 border border-[#9AAEAA] px-3 text-sm" placeholder="Frecuencia, ej. cada 8 h" aria-label="Frecuencia" /><input v-model="newPrescription.duration" class="h-10 border border-[#9AAEAA] px-3 text-sm" placeholder="Duración, ej. 5 días" aria-label="Duración" /><input v-model="newPrescription.instructions" class="h-10 border border-[#9AAEAA] px-3 text-sm md:col-span-2 xl:col-span-3" placeholder="Instrucciones adicionales (opcional)" aria-label="Instrucciones de la prescripción" /><button type="button" class="inline-flex h-10 items-center justify-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canAddPrescription" @click="addPrescription"><Plus class="h-4 w-4" /> Agregar</button></div><div v-if="form.prescriptions.length" class="divide-y divide-[#D8E0DE] border border-[#D8E0DE]"><div v-for="(prescription, index) in form.prescriptions" :key="`${prescription.medication_name}-${index}`" class="flex items-start justify-between gap-3 p-3"><div><p class="text-sm font-semibold text-[#131B2E]">{{ prescription.medication_name }} · {{ prescription.dosage }}</p><p class="mt-1 text-xs text-[#64748B]">{{ prescription.frequency }} durante {{ prescription.duration }}<template v-if="prescription.instructions"> · {{ prescription.instructions }}</template></p></div><button type="button" class="text-[#B42318]" aria-label="Eliminar prescripción" @click="removePrescription(index)"><Trash2 class="h-4 w-4" /></button></div></div><p v-else class="text-sm text-[#64748B]">Sin medicamentos prescritos.</p></div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-6">
                    <section class="border border-[#BDC9C6] bg-white p-5 shadow-sm"><h2 class="flex items-center gap-2 font-bold text-[#131B2E]"><UserRound class="h-5 w-5 text-[#005C55]" /> Seguridad del paciente</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-[#64748B]">Paciente</dt><dd class="font-semibold text-[#263633]">{{ patient.full_name }}</dd></div><div><dt class="text-xs text-[#64748B]">Historia clínica</dt><dd class="font-mono font-semibold text-[#263633]">{{ patient.record_number }}</dd></div><div><dt class="text-xs text-[#64748B]">Alergias</dt><dd class="font-semibold" :class="patient.medical_history?.allergies?.length ? 'text-[#B42318]' : 'text-[#006B63]'">{{ patient.medical_history?.allergies?.join(', ') || 'No registradas' }}</dd></div><div><dt class="text-xs text-[#64748B]">Medicamentos actuales</dt><dd class="font-semibold text-[#263633]">{{ patient.medical_history?.current_medications?.join(', ') || 'No registrados' }}</dd></div></dl></section>
                    <section class="border border-[#B7D9D4] bg-[#F1FAF8] p-5"><h2 class="flex items-center gap-2 font-bold text-[#005C55]"><CheckCircle2 class="h-5 w-5" /> Flujo de integridad</h2><ol class="mt-4 space-y-3 text-sm text-[#455653]"><li class="flex gap-2"><b class="font-mono text-[#005C55]">01</b><span>Guarda esta evolución como borrador editable.</span></li><li class="flex gap-2"><b class="font-mono text-[#005C55]">02</b><span>Revisa el registro completo en la pantalla siguiente.</span></li><li class="flex gap-2"><b class="font-mono text-[#005C55]">03</b><span>Finaliza para sellarlo con firma e integridad SHA-256.</span></li></ol></section>
                    <div v-if="Object.keys(form.errors).length" class="border border-[#FDA29B] bg-[#FFF5F4] p-4 text-sm text-[#B42318]"><p class="font-bold">No pudimos guardar el borrador.</p><p class="mt-1 text-xs">Revisa los campos marcados y vuelve a intentarlo.</p></div>
                    <div class="grid gap-2"><button type="submit" :disabled="form.processing" class="inline-flex h-11 items-center justify-center gap-2 bg-[#005C55] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#004B46] disabled:opacity-60"><Save class="h-4 w-4" /> {{ form.processing ? 'Guardando…' : 'Guardar borrador' }}</button><Link :href="`/patients/${patient.id}`" class="inline-flex h-10 items-center justify-center border border-[#9AAEAA] bg-white text-sm font-semibold text-[#455653]">Cancelar</Link></div>
                </aside>
            </form>
        </div>
    </ClinicLayout>
</template>
