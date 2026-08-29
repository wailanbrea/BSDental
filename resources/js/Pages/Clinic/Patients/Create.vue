<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import {
    AlertCircle,
    ArrowLeft,
    BadgeCheck,
    BriefcaseMedical,
    Check,
    ContactRound,
    FileCheck,
    HeartPulse,
    LoaderCircle,
    Plus,
    ShieldAlert,
    UserRoundPen,
    UsersRound,
    X,
} from 'lucide-vue-next'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'

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

const isEditing = computed(() => Boolean(props.patient))
const returnUrl = computed(() => props.patient ? `/patients/${props.patient.id}` : '/patients')
const medicalHistory = props.patient?.medical_history

const form = useForm({
    first_name: props.patient?.first_name ?? '',
    last_name: props.patient?.last_name ?? '',
    identification_type: props.patient?.identification_type ?? 'CEDULA',
    identification_number: props.patient?.identification_number ?? '',
    birth_date: props.patient?.birth_date?.slice(0, 10) ?? '',
    gender: props.patient?.gender ?? '',
    phone: props.patient?.phone ?? '',
    secondary_phone: props.patient?.secondary_phone ?? '',
    email: props.patient?.email ?? '',
    address: props.patient?.address ?? '',
    city: props.patient?.city ?? '',
    blood_type: props.patient?.blood_type ?? '',
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
    tags: [...(props.patient?.tags ?? [])],
    allergies: [...(medicalHistory?.allergies ?? [])],
    systemic_conditions: [...(medicalHistory?.systemic_conditions ?? [])],
    current_medications: [...(medicalHistory?.current_medications ?? [])],
    is_pregnant: medicalHistory?.is_pregnant ?? false,
    pregnancy_weeks: medicalHistory?.pregnancy_weeks ?? null as number | null,
    bleeding_disorders: medicalHistory?.bleeding_disorders ?? false,
    has_pacemaker: medicalHistory?.has_pacemaker ?? false,
    medical_notes: medicalHistory?.medical_notes ?? '',
})

const duplicateCandidates = ref<DuplicateCandidate[]>([])
const checkingDuplicates = ref(false)
const newAllergy = ref('')
const newCondition = ref('')
const newMedication = ref('')
const newTag = ref('')
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let keepAliveTimer: ReturnType<typeof setInterval> | null = null

function keepSessionAlive() {
    void fetch(appUrl('/session/keep-alive'), {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    })
}

onMounted(() => {
    keepSessionAlive()
    keepAliveTimer = setInterval(keepSessionAlive, 15 * 60 * 1000)
})

onUnmounted(() => {
    if (keepAliveTimer) clearInterval(keepAliveTimer)
    if (debounceTimer) clearTimeout(debounceTimer)
})

function addUnique(target: string[], input: string) {
    const value = input.trim()
    if (value && !target.some((item) => item.toLocaleLowerCase() === value.toLocaleLowerCase())) {
        target.push(value)
    }
}

function addAllergy() { addUnique(form.allergies, newAllergy.value); newAllergy.value = '' }
function addCondition() { addUnique(form.systemic_conditions, newCondition.value); newCondition.value = '' }
function addMedication() { addUnique(form.current_medications, newMedication.value); newMedication.value = '' }
function addTag() { addUnique(form.tags, newTag.value); newTag.value = '' }

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

            const response = await fetch(`/patients/check-duplicates?${params.toString()}`)
            const data = await response.json()
            duplicateCandidates.value = data.candidates || []
        } catch {
            duplicateCandidates.value = []
        } finally {
            checkingDuplicates.value = false
        }
    }, 400)
}

watch(
    [() => form.identification_number, () => form.phone, () => form.first_name, () => form.last_name],
    triggerDuplicateCheck,
)

watch(() => form.is_pregnant, (isPregnant) => {
    if (!isPregnant) form.pregnancy_weeks = null
})

watch(() => form.is_minor, (isMinor) => {
    if (!isMinor) {
        form.guardian_name = ''
        form.guardian_identification = ''
        form.guardian_phone = ''
    }
})

function submit() {
    if (props.patient) {
        form.put(appUrl(`/patients/${props.patient.id}`), { preserveScroll: true })
        return
    }

    form.post(appUrl('/patients'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="isEditing ? 'Editar paciente — BSDental' : 'Nuevo paciente — BSDental'" />

    <ClinicLayout>
        <div class="mx-auto max-w-7xl space-y-5">
            <header class="flex flex-col gap-4 border-b border-[#BDC9C6] pb-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <Link :href="returnUrl" class="mb-3 inline-flex items-center gap-2 text-xs font-semibold text-[#52615E] hover:text-[#005C55]">
                        <ArrowLeft class="h-4 w-4" />
                        {{ isEditing ? 'Volver a la Ficha 360' : 'Volver al directorio' }}
                    </Link>
                    <div class="flex items-center gap-3">
                        <div class="grid h-11 w-11 place-items-center rounded-lg bg-[#D8ECE9] text-[#005C55]">
                            <UserRoundPen class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#007D73]">Admisión clínica</p>
                            <h1 class="text-2xl font-semibold tracking-tight text-[#131B2E]">{{ isEditing ? 'Editar paciente' : 'Registrar nuevo paciente' }}</h1>
                        </div>
                    </div>
                </div>
                <div class="border border-[#BDC9C6] bg-white px-4 py-3 text-right">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Historia clínica</p>
                    <p class="mt-1 font-mono text-base font-bold text-[#005C55]">{{ suggestedRecordNumber }}</p>
                </div>
            </header>

            <div v-if="duplicateCandidates.length" class="border border-[#FEC84B] bg-[#FFFAEB] p-4">
                <div class="flex items-start gap-3">
                    <AlertCircle class="mt-0.5 h-5 w-5 shrink-0 text-[#B54708]" />
                    <div class="flex-1">
                        <h2 class="text-sm font-bold text-[#93370D]">Posible paciente duplicado</h2>
                        <p class="mt-1 text-xs text-[#7A2E0E]">Verifica estas coincidencias antes de guardar una historia nueva.</p>
                        <div class="mt-3 grid gap-2 md:grid-cols-2">
                            <Link v-for="candidate in duplicateCandidates" :key="candidate.id" :href="`/patients/${candidate.id}`" target="_blank" class="flex items-center justify-between border border-[#FEC84B] bg-white p-3 text-sm hover:border-[#B54708]">
                                <span><strong class="block text-[#131B2E]">{{ candidate.full_name }}</strong><span class="font-mono text-xs text-[#667085]">{{ candidate.record_number }} · {{ candidate.identification_number || candidate.phone || 'Sin identificación' }}</span></span>
                                <span class="font-semibold text-[#006B63]">Ver ficha →</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <section class="border border-[#D8E0DE] bg-white">
                    <div class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F1F5F9] px-5 py-3">
                        <FileCheck class="h-5 w-5 text-[#007D73]" />
                        <div><h2 class="font-semibold text-[#131B2E]">Identificación y contacto</h2><p class="text-xs text-[#667085]">Datos civiles y canales principales del paciente.</p></div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2 xl:grid-cols-4">
                        <label class="field"><span>Nombres *</span><input v-model="form.first_name" required autocomplete="given-name" /><small v-if="form.errors.first_name">{{ form.errors.first_name }}</small></label>
                        <label class="field"><span>Apellidos *</span><input v-model="form.last_name" required autocomplete="family-name" /><small v-if="form.errors.last_name">{{ form.errors.last_name }}</small></label>
                        <label class="field"><span>Tipo de documento</span><select v-model="form.identification_type"><option value="">Sin especificar</option><option value="CEDULA">Cédula</option><option value="DNI">DNI</option><option value="PASSPORT">Pasaporte</option><option value="RUT">RUT</option></select></label>
                        <label class="field"><span>Número de documento</span><div class="relative"><input v-model="form.identification_number" class="w-full pr-9" autocomplete="off" /><LoaderCircle v-if="checkingDuplicates" class="absolute right-3 top-2.5 h-4 w-4 animate-spin text-[#007D73]" /></div></label>
                        <label class="field"><span>Fecha de nacimiento</span><input v-model="form.birth_date" type="date" /></label>
                        <label class="field"><span>Género</span><select v-model="form.gender"><option value="">Sin especificar</option><option value="male">Masculino</option><option value="female">Femenino</option><option value="other">Otro</option></select></label>
                        <label class="field"><span>Grupo sanguíneo</span><select v-model="form.blood_type"><option value="">Sin registrar</option><option v-for="type in ['O+','O-','A+','A-','B+','B-','AB+','AB-']" :key="type" :value="type">{{ type }}</option></select></label>
                        <label class="field"><span>Ciudad</span><input v-model="form.city" autocomplete="address-level2" /></label>
                        <label class="field"><span>Teléfono principal</span><input v-model="form.phone" type="tel" autocomplete="tel" /><small v-if="form.errors.phone">{{ form.errors.phone }}</small></label>
                        <label class="field"><span>Teléfono secundario</span><input v-model="form.secondary_phone" type="tel" /></label>
                        <label class="field md:col-span-2"><span>Correo electrónico</span><input v-model="form.email" type="email" autocomplete="email" /><small v-if="form.errors.email">{{ form.errors.email }}</small></label>
                        <label class="field md:col-span-2 xl:col-span-4"><span>Dirección residencial</span><input v-model="form.address" autocomplete="street-address" /></label>
                    </div>
                </section>

                <div class="grid gap-5 xl:grid-cols-2">
                    <section class="border border-[#D8E0DE] bg-white">
                        <div class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F1F5F9] px-5 py-3"><ContactRound class="h-5 w-5 text-[#455A73]" /><div><h2 class="font-semibold">Contacto de emergencia</h2><p class="text-xs text-[#667085]">Persona a contactar ante una eventualidad.</p></div></div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <label class="field md:col-span-2"><span>Nombre completo</span><input v-model="form.emergency_contact_name" /></label>
                            <label class="field"><span>Teléfono</span><input v-model="form.emergency_contact_phone" type="tel" /></label>
                            <label class="field"><span>Relación</span><input v-model="form.emergency_contact_relationship" placeholder="Ej. cónyuge, madre" /></label>
                        </div>
                    </section>

                    <section class="border border-[#D8E0DE] bg-white">
                        <div class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F1F5F9] px-5 py-3"><BriefcaseMedical class="h-5 w-5 text-[#455A73]" /><div><h2 class="font-semibold">Cobertura y seguro</h2><p class="text-xs text-[#667085]">Información administrativa opcional.</p></div></div>
                        <div class="grid gap-4 p-5 md:grid-cols-2">
                            <label class="field"><span>Aseguradora</span><input v-model="form.insurance_company" /></label>
                            <label class="field"><span>Número de póliza</span><input v-model="form.insurance_policy_number" /></label>
                        </div>
                    </section>
                </div>

                <section class="border border-[#D8E0DE] bg-white">
                    <div class="flex items-center justify-between gap-3 border-b border-[#D8E0DE] bg-[#FFF9F8] px-5 py-3">
                        <div class="flex items-center gap-3"><ShieldAlert class="h-5 w-5 text-[#B42318]" /><div><h2 class="font-semibold text-[#131B2E]">Alertas médicas y anamnesis</h2><p class="text-xs text-[#667085]">Información crítica visible durante la atención.</p></div></div>
                        <span class="hidden border border-[#F5A3A0] bg-white px-2 py-1 text-[11px] font-bold uppercase tracking-wide text-[#B42318] md:inline">Información sensible</span>
                    </div>
                    <div class="grid gap-5 p-5 lg:grid-cols-3">
                        <div class="chip-editor"><label>Alergias</label><div class="flex gap-2"><input v-model="newAllergy" placeholder="Ej. Penicilina" @keydown.enter.prevent="addAllergy" /><button type="button" aria-label="Añadir alergia" @click="addAllergy"><Plus class="h-4 w-4" /></button></div><div class="chips"><span v-for="(item, index) in form.allergies" :key="item" class="chip chip-danger">{{ item }}<button type="button" :aria-label="`Quitar ${item}`" @click="form.allergies.splice(index, 1)"><X class="h-3 w-3" /></button></span><small v-if="!form.allergies.length">Sin alergias registradas</small></div></div>
                        <div class="chip-editor"><label>Condiciones sistémicas</label><div class="flex gap-2"><input v-model="newCondition" placeholder="Ej. Hipertensión" @keydown.enter.prevent="addCondition" /><button type="button" aria-label="Añadir condición" @click="addCondition"><Plus class="h-4 w-4" /></button></div><div class="chips"><span v-for="(item, index) in form.systemic_conditions" :key="item" class="chip chip-warning">{{ item }}<button type="button" :aria-label="`Quitar ${item}`" @click="form.systemic_conditions.splice(index, 1)"><X class="h-3 w-3" /></button></span><small v-if="!form.systemic_conditions.length">Sin condiciones registradas</small></div></div>
                        <div class="chip-editor"><label>Medicamentos actuales</label><div class="flex gap-2"><input v-model="newMedication" placeholder="Ej. Losartán 50 mg" @keydown.enter.prevent="addMedication" /><button type="button" aria-label="Añadir medicamento" @click="addMedication"><Plus class="h-4 w-4" /></button></div><div class="chips"><span v-for="(item, index) in form.current_medications" :key="item" class="chip chip-neutral">{{ item }}<button type="button" :aria-label="`Quitar ${item}`" @click="form.current_medications.splice(index, 1)"><X class="h-3 w-3" /></button></span><small v-if="!form.current_medications.length">Sin medicamentos registrados</small></div></div>
                    </div>
                    <div class="grid gap-3 border-t border-[#D8E0DE] bg-[#F8FAFC] p-5 md:grid-cols-3">
                        <label class="check-card"><input v-model="form.has_pacemaker" type="checkbox" /><HeartPulse class="h-5 w-5" /><span><strong>Marcapasos</strong><small>Portador de dispositivo cardíaco</small></span></label>
                        <label class="check-card"><input v-model="form.bleeding_disorders" type="checkbox" /><BadgeCheck class="h-5 w-5" /><span><strong>Coagulación</strong><small>Trastorno de sangrado registrado</small></span></label>
                        <div class="space-y-2"><label class="check-card"><input v-model="form.is_pregnant" type="checkbox" /><UsersRound class="h-5 w-5" /><span><strong>Gestación</strong><small>Paciente actualmente embarazada</small></span></label><label v-if="form.is_pregnant" class="field"><span>Semanas de gestación</span><input v-model.number="form.pregnancy_weeks" type="number" min="1" max="42" /></label></div>
                    </div>
                    <div class="p-5 pt-0"><label class="field"><span>Notas médicas</span><textarea v-model="form.medical_notes" rows="3" placeholder="Antecedentes, observaciones o precauciones adicionales"></textarea></label></div>
                </section>

                <section class="border border-[#D8E0DE] bg-white">
                    <div class="flex items-center gap-3 border-b border-[#D8E0DE] bg-[#F1F5F9] px-5 py-3"><UsersRound class="h-5 w-5 text-[#455A73]" /><div><h2 class="font-semibold">Responsable y clasificación</h2><p class="text-xs text-[#667085]">Tutor legal, etiquetas operativas y notas internas.</p></div></div>
                    <div class="space-y-5 p-5">
                        <label class="inline-flex items-center gap-3 text-sm font-semibold text-[#344054]"><input v-model="form.is_minor" type="checkbox" class="h-4 w-4 accent-[#007D73]" /> Paciente menor de edad o bajo representación legal</label>
                        <div v-if="form.is_minor" class="grid gap-4 border border-[#B7D9D4] bg-[#F1FAF8] p-4 md:grid-cols-3">
                            <label class="field"><span>Nombre del tutor *</span><input v-model="form.guardian_name" :required="form.is_minor" /></label>
                            <label class="field"><span>Identificación del tutor</span><input v-model="form.guardian_identification" /></label>
                            <label class="field"><span>Teléfono del tutor *</span><input v-model="form.guardian_phone" type="tel" :required="form.is_minor" /></label>
                        </div>
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="chip-editor"><label>Etiquetas</label><div class="flex gap-2"><input v-model="newTag" placeholder="Ej. VIP, Ortodoncia" @keydown.enter.prevent="addTag" /><button type="button" aria-label="Añadir etiqueta" @click="addTag"><Plus class="h-4 w-4" /></button></div><div class="chips"><span v-for="(item, index) in form.tags" :key="item" class="chip chip-primary">{{ item }}<button type="button" :aria-label="`Quitar ${item}`" @click="form.tags.splice(index, 1)"><X class="h-3 w-3" /></button></span><small v-if="!form.tags.length">Sin etiquetas</small></div></div>
                            <label class="field"><span>Notas administrativas</span><textarea v-model="form.notes" rows="3" placeholder="Preferencias, instrucciones o contexto administrativo"></textarea></label>
                        </div>
                    </div>
                </section>

                <div v-if="Object.keys(form.errors).length" class="flex items-start gap-3 border border-[#F5A3A0] bg-[#FFF1F0] p-4 text-sm text-[#912018]">
                    <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" />
                    <div><strong>Revisa los campos marcados.</strong><p class="mt-1 text-xs">No se guardó información porque hay datos inválidos o incompletos.</p></div>
                </div>

                <footer class="sticky bottom-0 z-10 flex flex-col-reverse gap-3 border border-[#D8E0DE] bg-white/95 p-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-[#667085]">Los cambios clínicos quedan vinculados a la auditoría del tenant.</p>
                    <div class="flex gap-3">
                        <Link :href="returnUrl" class="inline-flex h-10 items-center justify-center border border-[#9AAEAA] bg-white px-5 text-sm font-semibold text-[#344054] hover:bg-[#F1F5F9]">Cancelar</Link>
                        <button type="submit" :disabled="form.processing" class="inline-flex h-10 items-center justify-center gap-2 bg-[#005C55] px-5 text-sm font-semibold text-white hover:bg-[#004C47] disabled:cursor-not-allowed disabled:opacity-50">
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <Check v-else class="h-4 w-4" />
                            {{ isEditing ? 'Guardar cambios' : 'Registrar y abrir Ficha 360' }}
                        </button>
                    </div>
                </footer>
            </form>
        </div>
    </ClinicLayout>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: 0.375rem; }
.field > span, .chip-editor > label { color: #52615e; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.02em; }
.field input, .field select, .field textarea, .chip-editor input { width: 100%; border: 1px solid #bdc9c6; border-radius: 0.25rem; background: #fff; color: #131b2e; font-size: 0.875rem; outline: none; transition: border-color 150ms; }
.field input, .field select, .chip-editor input { height: 2.5rem; padding: 0 0.75rem; }
.field textarea { padding: 0.625rem 0.75rem; resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus, .chip-editor input:focus { border-color: #007d73; box-shadow: inset 0 0 0 1px #007d73; }
.field small { color: #ba1a1a; font-size: 0.6875rem; }
.chip-editor { display: flex; flex-direction: column; gap: 0.5rem; }
.chip-editor > div:first-of-type > button { display: grid; height: 2.5rem; width: 2.5rem; flex: none; place-items: center; border-radius: 0.25rem; background: #005c55; color: #fff; }
.chips { display: flex; min-height: 1.75rem; flex-wrap: wrap; gap: 0.375rem; }
.chips > small { align-self: center; color: #667085; font-size: 0.75rem; }
.chip { display: inline-flex; align-items: center; gap: 0.25rem; border: 1px solid; padding: 0.25rem 0.5rem; font-size: 0.75rem; font-weight: 600; }
.chip button { opacity: 0.7; }
.chip button:hover { opacity: 1; }
.chip-danger { border-color: #f5a3a0; background: #fff1f0; color: #912018; }
.chip-warning { border-color: #fec84b; background: #fffaeb; color: #93370d; }
.chip-neutral { border-color: #bdc9c6; background: #f1f5f9; color: #344054; }
.chip-primary { border-color: #9aaeaa; background: #d8ece9; color: #005c55; }
.check-card { display: flex; min-height: 4rem; cursor: pointer; align-items: center; gap: 0.75rem; border: 1px solid #bdc9c6; background: #fff; padding: 0.75rem; color: #455653; }
.check-card:has(input:checked) { border-color: #007d73; background: #f1faf8; color: #005c55; }
.check-card input { height: 1rem; width: 1rem; accent-color: #007d73; }
.check-card span { display: flex; flex-direction: column; }
.check-card strong { font-size: 0.8125rem; }
.check-card small { color: #667085; font-size: 0.6875rem; font-weight: 400; }
</style>
