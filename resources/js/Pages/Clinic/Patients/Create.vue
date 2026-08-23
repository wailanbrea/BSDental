<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { UserPlus, ArrowLeft, AlertCircle, ShieldAlert, FileCheck, UserRoundPen } from 'lucide-vue-next'

interface DuplicateCandidate {
    id: string
    record_number: string
    full_name: string
    identification_number: string | null
    phone: string | null
}

interface EditablePatient {
    id: string
    record_number: string
    first_name: string
    last_name: string
    identification_type: string | null
    identification_number: string | null
    birth_date: string | null
    gender: string | null
    phone: string | null
    secondary_phone: string | null
    email: string | null
    address: string | null
    city: string | null
    blood_type: string | null
    emergency_contact_name: string | null
    emergency_contact_phone: string | null
    emergency_contact_relationship: string | null
    is_minor: boolean
    guardian_name: string | null
    guardian_identification: string | null
    guardian_phone: string | null
    insurance_company: string | null
    insurance_policy_number: string | null
    notes: string | null
    tags: string[] | null
    medical_history: {
        allergies: string[] | null
        systemic_conditions: string[] | null
        current_medications: string[] | null
        is_pregnant: boolean
        pregnancy_weeks: number | null
        bleeding_disorders: boolean
        has_pacemaker: boolean
        medical_notes: string | null
    } | null
}

const props = defineProps<{
    suggestedRecordNumber: string
    patient?: EditablePatient
}>()

const isEditing = Boolean(props.patient)
const medicalHistory = props.patient?.medical_history

const form = useForm({
    first_name: props.patient?.first_name ?? '',
    last_name: props.patient?.last_name ?? '',
    identification_type: props.patient?.identification_type ?? 'CEDULA',
    identification_number: props.patient?.identification_number ?? '',
    birth_date: props.patient?.birth_date?.slice(0, 10) ?? '',
    gender: props.patient?.gender ?? 'male',
    phone: props.patient?.phone ?? '',
    secondary_phone: props.patient?.secondary_phone ?? '',
    email: props.patient?.email ?? '',
    address: props.patient?.address ?? '',
    city: props.patient?.city ?? '',
    blood_type: props.patient?.blood_type ?? 'O+',
    emergency_contact_name: props.patient?.emergency_contact_name ?? '',
    emergency_contact_phone: props.patient?.emergency_contact_phone ?? '',
    emergency_contact_relationship: props.patient?.emergency_contact_relationship ?? '',
    is_minor: props.patient?.is_minor ?? false,
    guardian_name: props.patient?.guardian_name ?? '',
    guardian_identification: props.patient?.guardian_identification ?? '',
    guardian_phone: props.patient?.guardian_phone ?? '',
    insurance_company: props.patient?.insurance_company ?? '',
    insurance_policy_number: props.patient?.insurance_policy_number ?? '',
    notes: props.patient?.notes ?? '',
    tags: props.patient?.tags ?? [],
    // Anamnesis / Alertas
    allergies: medicalHistory?.allergies ?? [] as string[],
    new_allergy: '',
    systemic_conditions: medicalHistory?.systemic_conditions ?? [] as string[],
    new_condition: '',
    current_medications: medicalHistory?.current_medications ?? [] as string[],
    new_medication: '',
    is_pregnant: medicalHistory?.is_pregnant ?? false,
    pregnancy_weeks: medicalHistory?.pregnancy_weeks ?? null as number | null,
    bleeding_disorders: medicalHistory?.bleeding_disorders ?? false,
    has_pacemaker: medicalHistory?.has_pacemaker ?? false,
    medical_notes: medicalHistory?.medical_notes ?? '',
})

const duplicateCandidates = ref<DuplicateCandidate[]>([])
const checkingDuplicates = ref(false)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function triggerDuplicateCheck() {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(async () => {
        if (!form.identification_number && !form.phone && (!form.first_name || !form.last_name)) {
            duplicateCandidates.value = []
            return
        }

        checkingDuplicates.value = true
        try {
            const params = new URLSearchParams({
                identification_number: form.identification_number,
                phone: form.phone,
                first_name: form.first_name,
                last_name: form.last_name,
            })
            if (props.patient) params.set('ignore_id', props.patient.id)
            const res = await fetch(`/patients/check-duplicates?${params.toString()}`)
            const data = await res.json()
            duplicateCandidates.value = data.candidates || []
        } catch {
            duplicateCandidates.value = []
        } finally {
            checkingDuplicates.value = false
        }
    }, 400)
}

watch([() => form.identification_number, () => form.phone, () => form.first_name, () => form.last_name], () => {
    triggerDuplicateCheck()
})

function addAllergy() {
    if (form.new_allergy.trim()) {
        form.allergies.push(form.new_allergy.trim())
        form.new_allergy = ''
    }
}

function removeAllergy(index: number) {
    form.allergies.splice(index, 1)
}

function addCondition() {
    if (form.new_condition.trim()) {
        form.systemic_conditions.push(form.new_condition.trim())
        form.new_condition = ''
    }
}

function removeCondition(index: number) {
    form.systemic_conditions.splice(index, 1)
}

function addMedication() {
    if (form.new_medication.trim()) {
        form.current_medications.push(form.new_medication.trim())
        form.new_medication = ''
    }
}

function removeMedication(index: number) {
    form.current_medications.splice(index, 1)
}

function submit() {
    if (props.patient) {
        form.put(`/patients/${props.patient.id}`)
        return
    }

    form.post('/patients')
}
</script>

<template>
    <Head :title="isEditing ? 'Editar Paciente — BSDental' : 'Registrar Nuevo Paciente — BSDental'" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <UserRoundPen v-if="isEditing" class="w-6 h-6" />
                        <UserPlus v-else class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">{{ isEditing ? 'Editar Paciente' : 'Nuevo Paciente' }}</h1>
                        <p class="text-sm text-slate-400">Historia Clínica: <span class="font-mono font-bold text-teal-400">{{ props.suggestedRecordNumber }}</span></p>
                    </div>
                </div>

                <a :href="isEditing && props.patient ? `/patients/${props.patient.id}` : '/patients'" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                    <ArrowLeft class="w-4 h-4" /> {{ isEditing ? 'Volver a la Ficha 360' : 'Volver al Directorio' }}
                </a>
            </div>

            <!-- Duplicate Candidate Warning Banner -->
            <div v-if="duplicateCandidates.length > 0" class="p-5 bg-amber-500/10 border border-amber-500/30 rounded-2xl space-y-3">
                <div class="flex items-center gap-2 text-amber-400 font-semibold text-sm">
                    <AlertCircle class="w-5 h-5" />
                    <span>Posible Paciente Duplicado Detectado</span>
                </div>
                <p class="text-xs text-slate-300">Se encontraron coincidencias existentes con los datos ingresados:</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div v-for="dup in duplicateCandidates" :key="dup.id" class="p-3 bg-slate-900/80 border border-amber-500/20 rounded-xl flex items-center justify-between">
                        <div>
                            <div class="font-bold text-sm text-white">{{ dup.full_name }}</div>
                            <div class="text-xs text-slate-400 font-mono">HC: {{ dup.record_number }} | Doc: {{ dup.identification_number || 'S/N' }}</div>
                        </div>
                        <a :href="`/patients/${dup.id}`" target="_blank" class="text-xs text-teal-400 hover:underline">Ver Ficha →</a>
                    </div>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- Seccion 1: Datos Personales -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <FileCheck class="w-5 h-5 text-teal-400" /> Datos de Identificación y Contacto
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Nombres *</label>
                            <input v-model="form.first_name" type="text" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Apellidos *</label>
                            <input v-model="form.last_name" type="text" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tipo de Documento</label>
                            <select v-model="form.identification_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none">
                                <option value="CEDULA">Cédula de Identidad</option>
                                <option value="DNI">DNI</option>
                                <option value="PASSPORT">Pasaporte</option>
                                <option value="RUT">RUT</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Número de Documento</label>
                            <input v-model="form.identification_number" type="text" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Fecha de Nacimiento</label>
                            <input v-model="form.birth_date" type="date" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Género</label>
                            <select v-model="form.gender" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none">
                                <option value="male">Masculino</option>
                                <option value="female">Femenino</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Teléfono Principal</label>
                            <input v-model="form.phone" type="text" placeholder="+58 412 000-0000" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Teléfono Secundario</label>
                            <input v-model="form.secondary_phone" type="text" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Correo Electrónico</label>
                            <input v-model="form.email" type="email" placeholder="paciente@correo.com" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>

                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Dirección Residencial</label>
                            <input v-model="form.address" type="text" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Grupo Sanguíneo</label>
                            <select v-model="form.blood_type" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:border-teal-500 focus:outline-none">
                                <option value="O+">O Positivo (O+)</option>
                                <option value="O-">O Negativo (O-)</option>
                                <option value="A+">A Positivo (A+)</option>
                                <option value="A-">A Negativo (A-)</option>
                                <option value="B+">B Positivo (B+)</option>
                                <option value="B-">B Negativo (B-)</option>
                                <option value="AB+">AB Positivo (AB+)</option>
                                <option value="AB-">AB Negativo (AB-)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Seccion 2: Antecedentes y Alertas Médicas (Anamnesis) -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <ShieldAlert class="w-5 h-5 text-amber-400" /> Alertas Médicas y Anamnesis
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Alergias -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Alergias Medicamentosas / Materiales</label>
                            <div class="flex gap-2 mb-2">
                                <input v-model="form.new_allergy" type="text" placeholder="Ej. Penicilina" class="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white" @keydown.enter.prevent="addAllergy" />
                                <button type="button" class="px-3 py-1.5 bg-amber-500/20 text-amber-300 text-xs font-bold rounded-lg border border-amber-500/30" @click="addAllergy">+</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(al, i) in form.allergies" :key="i" class="px-2 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/30 rounded-lg text-xs flex items-center gap-1">
                                    {{ al }} <button type="button" class="hover:text-white" @click="removeAllergy(i)">×</button>
                                </span>
                            </div>
                        </div>

                        <!-- Condiciones Sistemicas -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Condiciones Sistémicas</label>
                            <div class="flex gap-2 mb-2">
                                <input v-model="form.new_condition" type="text" placeholder="Ej. Hipertensión" class="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white" @keydown.enter.prevent="addCondition" />
                                <button type="button" class="px-3 py-1.5 bg-teal-500/20 text-teal-300 text-xs font-bold rounded-lg border border-teal-500/30" @click="addCondition">+</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(cond, i) in form.systemic_conditions" :key="i" class="px-2 py-1 bg-teal-500/10 text-teal-400 border border-teal-500/30 rounded-lg text-xs flex items-center gap-1">
                                    {{ cond }} <button type="button" class="hover:text-white" @click="removeCondition(i)">×</button>
                                </span>
                            </div>
                        </div>

                        <!-- Medicacion Habitual -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Medicamentos Actuales</label>
                            <div class="flex gap-2 mb-2">
                                <input v-model="form.new_medication" type="text" placeholder="Ej. Aspirina 100mg" class="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-white" @keydown.enter.prevent="addMedication" />
                                <button type="button" class="px-3 py-1.5 bg-slate-700 text-slate-200 text-xs font-bold rounded-lg" @click="addMedication">+</button>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-for="(med, i) in form.current_medications" :key="i" class="px-2 py-1 bg-slate-900 text-slate-300 border border-slate-700 rounded-lg text-xs flex items-center gap-1">
                                    {{ med }} <button type="button" class="hover:text-white" @click="removeMedication(i)">×</button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Boolean Alerts -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-4 border-t border-slate-700/60">
                        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input v-model="form.has_pacemaker" type="checkbox" class="rounded bg-slate-900 border-slate-700 text-teal-500" />
                            Portador de Marcapasos
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input v-model="form.bleeding_disorders" type="checkbox" class="rounded bg-slate-900 border-slate-700 text-teal-500" />
                            Trastornos de Coagulación / Sangrado
                        </label>
                        <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                            <input v-model="form.is_pregnant" type="checkbox" class="rounded bg-slate-900 border-slate-700 text-teal-500" />
                            Paciente en Estado de Gestación
                        </label>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="flex justify-end gap-3 pt-4">
                    <a :href="isEditing && props.patient ? `/patients/${props.patient.id}` : '/patients'" class="px-6 py-2.5 bg-slate-800 text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-700 transition">Cancelar</a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition disabled:opacity-50"
                    >
                        {{ isEditing ? 'Guardar Cambios' : 'Guardar Paciente y Abrir Ficha 360' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
