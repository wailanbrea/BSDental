<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { computed, nextTick, ref } from 'vue'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { AlertTriangle, ArrowLeft, Check, CheckCircle2, ChevronRight, Eye, FileCheck2, FileSignature, History, PenLine, RotateCcw, ShieldAlert, ShieldCheck, UserRoundCheck, X } from 'lucide-vue-next'

defineOptions({ layout: ClinicLayout })

interface MedicalHistory { allergies: string[] | null; systemic_conditions: string[] | null }
interface PatientDetails { id: string; record_number: string; full_name: string; age: number | null; medical_history: MedicalHistory | null }
interface TemplateItem { id: string; title: string; slug: string; version: number; content: string; required_witness: boolean }
interface IntegrityStatus { status: 'verified' | 'legacy' | 'mismatch'; algorithm: string }
interface ConsentItem {
    id: string; title: string; template_version: number; signed_by_name: string; signed_by_identification: string | null
    relationship: string; signature_type: string; signed_at: string; integrity_hash: string; rendered_content: string; integrity: IntegrityStatus
}
interface DocumentBlock { type: 'heading' | 'bullet' | 'paragraph'; text: string }

const props = defineProps<{ patient: PatientDetails; templates: TemplateItem[]; consents: ConsentItem[]; selectedConsentId: string | null }>()
const selectedTemplate = ref<TemplateItem | null>(null)
const selectedConsent = ref<ConsentItem | null>(props.consents.find((consent) => consent.id === props.selectedConsentId) || null)
const isSigningModal = ref(false)
const signatureCanvas = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const hasSignature = ref(false)

const form = useForm({
    consent_template_id: '', signed_by_name: props.patient.full_name, signed_by_identification: '',
    relationship: 'patient', signature_type: 'drawn', signature_data: '', accepted_terms: false,
})

const allergies = computed(() => props.patient.medical_history?.allergies || [])
const systemicConditions = computed(() => props.patient.medical_history?.systemic_conditions || [])
const verifiedCount = computed(() => props.consents.filter((consent) => consent.integrity.status === 'verified').length)
const canSubmit = computed(() => hasSignature.value && form.accepted_terms && form.signed_by_name.trim() !== '' && form.signed_by_identification.trim() !== '')

function renderedTemplate(template: TemplateItem) {
    return template.content
        .replaceAll('{{patient_name}}', props.patient.full_name)
        .replaceAll('{{record_number}}', props.patient.record_number)
        .replaceAll('{{date}}', new Intl.DateTimeFormat('es-DO', { dateStyle: 'long' }).format(new Date()))
}

function documentBlocks(content: string): DocumentBlock[] {
    return content.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
        if (line.startsWith('#')) return { type: 'heading', text: line.replace(/^#+\s*/, '') }
        if (/^[-*]\s+/.test(line)) return { type: 'bullet', text: line.replace(/^[-*]\s+/, '') }
        return { type: 'paragraph', text: line }
    })
}

function summaryText(template: TemplateItem) {
    return documentBlocks(renderedTemplate(template)).map((block) => block.text).join(' ')
}

async function openSigning(template: TemplateItem) {
    selectedTemplate.value = template
    form.consent_template_id = template.id
    form.signed_by_name = props.patient.full_name
    form.signed_by_identification = ''
    form.relationship = 'patient'
    form.signature_type = 'drawn'
    form.signature_data = ''
    form.accepted_terms = false
    form.clearErrors()
    isSigningModal.value = true
    await nextTick()
    prepareCanvas()
}

function closeSigning() {
    isSigningModal.value = false
    selectedTemplate.value = null
    isDrawing.value = false
}

function prepareCanvas() {
    const canvas = signatureCanvas.value
    if (!canvas) return
    const rect = canvas.getBoundingClientRect()
    const ratio = window.devicePixelRatio || 1
    canvas.width = Math.max(1, Math.floor(rect.width * ratio))
    canvas.height = Math.max(1, Math.floor(rect.height * ratio))
    const context = canvas.getContext('2d')
    if (!context) return
    context.scale(ratio, ratio)
    context.fillStyle = '#FFFFFF'
    context.fillRect(0, 0, rect.width, rect.height)
    context.strokeStyle = '#005C55'
    context.lineWidth = 2.25
    context.lineCap = 'round'
    context.lineJoin = 'round'
    hasSignature.value = false
}

function point(event: PointerEvent) {
    const canvas = signatureCanvas.value
    if (!canvas) return { x: 0, y: 0 }
    const rect = canvas.getBoundingClientRect()
    return { x: event.clientX - rect.left, y: event.clientY - rect.top }
}

function startDrawing(event: PointerEvent) {
    const canvas = signatureCanvas.value
    const context = canvas?.getContext('2d')
    if (!canvas || !context) return
    isDrawing.value = true
    canvas.setPointerCapture(event.pointerId)
    const current = point(event)
    context.beginPath()
    context.moveTo(current.x, current.y)
}

function draw(event: PointerEvent) {
    if (!isDrawing.value) return
    const context = signatureCanvas.value?.getContext('2d')
    if (!context) return
    const current = point(event)
    context.lineTo(current.x, current.y)
    context.stroke()
}

function finishDrawing() {
    if (!isDrawing.value) return
    isDrawing.value = false
    const canvas = signatureCanvas.value
    if (!canvas) return
    hasSignature.value = true
    form.signature_data = canvas.toDataURL('image/png')
}

function clearSignature() {
    prepareCanvas()
    form.signature_data = ''
}

function submitSigning() {
    if (!canSubmit.value) return
    form.post(appUrl(`/patients/${props.patient.id}/consents`), {
        preserveScroll: true,
        onSuccess: () => {
            closeSigning()
            form.reset()
            form.signed_by_name = props.patient.full_name
        },
    })
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function relationshipLabel(value: string) {
    return { patient: 'Paciente', parent: 'Padre / madre', guardian: 'Tutor', legal_representative: 'Representante legal' }[value] || value
}

function integrityMeta(status: IntegrityStatus['status']) {
    if (status === 'verified') return { label: 'Sello verificado', classes: 'border-[#ABEFC6] bg-[#ECFDF3] text-[#027A48]' }
    if (status === 'legacy') return { label: 'Sello heredado', classes: 'border-[#FEDF89] bg-[#FFFAEB] text-[#B54708]' }
    return { label: 'Integridad comprometida', classes: 'border-[#FDA29B] bg-[#FEF3F2] text-[#B42318]' }
}
</script>

<template>
    <Head :title="`Consentimientos — ${patient.full_name}`" />
    <main class="space-y-5 bg-[#F8FAFC] p-4 text-[#131B2E] md:p-6">
        <nav class="flex flex-wrap items-center gap-2 text-xs text-[#64748B]">
            <Link :href="appUrl('/patients')" class="hover:text-[#005C55]">Pacientes</Link><ChevronRight class="h-3.5 w-3.5" />
            <Link :href="`/patients/${patient.id}`" class="hover:text-[#005C55]">{{ patient.full_name }}</Link><ChevronRight class="h-3.5 w-3.5" />
            <span class="font-semibold text-[#344054]">Consentimientos</span>
        </nav>

        <header class="flex flex-col gap-4 border border-[#D8E0DE] bg-white p-4 shadow-[0_4px_12px_rgba(15,23,42,0.04)] lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="grid h-12 w-12 shrink-0 place-items-center bg-[#D8ECE9] font-bold text-[#005C55]">{{ patient.full_name.charAt(0) }}</div>
                <div><div class="flex flex-wrap items-baseline gap-2"><h1 class="text-xl font-bold">{{ patient.full_name }}</h1><span class="font-mono text-xs text-[#64748B]">{{ patient.record_number }}</span></div><p class="mt-1 text-xs text-[#64748B]">{{ patient.age ? `${patient.age} años · ` : '' }}Consentimientos informados y evidencia de aceptación</p></div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span v-for="allergy in allergies" :key="allergy" class="inline-flex items-center gap-1.5 border border-[#FDA29B] bg-[#FEF3F2] px-2.5 py-1.5 text-xs font-semibold text-[#B42318]"><AlertTriangle class="h-3.5 w-3.5" />Alergia: {{ allergy }}</span>
                <span v-for="condition in systemicConditions" :key="condition" class="inline-flex items-center gap-1.5 border border-[#FEDF89] bg-[#FFFAEB] px-2.5 py-1.5 text-xs font-semibold text-[#B54708]">{{ condition }}</span>
                <Link :href="`/patients/${patient.id}`" class="inline-flex h-9 items-center gap-2 border border-[#9AAEAA] bg-white px-3 text-xs font-semibold text-[#344054] hover:bg-[#F2F4F7]"><ArrowLeft class="h-4 w-4" />Ficha 360</Link>
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Plantillas vigentes</p><p class="mt-2 font-mono text-2xl font-bold text-[#005C55]">{{ templates.length }}</p></div>
            <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Documentos firmados</p><p class="mt-2 font-mono text-2xl font-bold text-[#131B2E]">{{ consents.length }}</p></div>
            <div class="border border-[#D8E0DE] bg-white p-4"><p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Sellos verificados</p><p class="mt-2 font-mono text-2xl font-bold text-[#027A48]">{{ verifiedCount }}/{{ consents.length }}</p></div>
        </section>

        <section class="border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
            <header class="border-b border-[#D8E0DE] bg-[#F7FAF9] px-4 py-3"><h2 class="flex items-center gap-2 text-sm font-bold"><FileSignature class="h-4 w-4 text-[#005C55]" />Documentos disponibles para firma</h2><p class="mt-0.5 text-xs text-[#64748B]">Selecciona una plantilla, revisa el texto completo y captura la firma del paciente o representante.</p></header>
            <div v-if="templates.length" class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="template in templates" :key="template.id" class="flex min-h-40 flex-col border border-[#BDC9C6] bg-white p-4 transition hover:border-[#005C55] hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div class="grid h-9 w-9 shrink-0 place-items-center bg-[#D8ECE9] text-[#005C55]"><FileCheck2 class="h-5 w-5" /></div><span class="border border-[#D8E0DE] bg-[#F2F4F7] px-2 py-1 font-mono text-[10px] text-[#52615E]">v{{ template.version }}</span></div>
                    <h3 class="mt-3 text-sm font-bold leading-5">{{ template.title }}</h3><p class="mt-1 line-clamp-2 text-xs leading-5 text-[#64748B]">{{ summaryText(template) }}</p>
                    <div class="mt-auto flex items-center justify-between gap-2 pt-4"><span v-if="template.required_witness" class="text-[10px] font-semibold text-[#B54708]">Doble firma requerida</span><span v-else class="text-[10px] text-[#98A2B3]">Firma individual</span><button type="button" :disabled="template.required_witness" class="inline-flex h-9 items-center gap-2 bg-[#005C55] px-3 text-xs font-bold text-white disabled:cursor-not-allowed disabled:bg-[#D0D5DD]" :title="template.required_witness ? 'Requiere flujo de firma con testigo' : undefined" @click="openSigning(template)"><PenLine class="h-3.5 w-3.5" />{{ template.required_witness ? 'Requiere testigo' : 'Revisar y firmar' }}</button></div>
                </article>
            </div>
            <div v-else class="grid min-h-36 place-items-center p-6 text-center"><p class="text-sm text-[#64748B]">No hay plantillas activas para esta clínica.</p></div>
        </section>

        <section class="border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
            <header class="border-b border-[#D8E0DE] bg-[#F7FAF9] px-4 py-3"><h2 class="flex items-center gap-2 text-sm font-bold"><History class="h-4 w-4 text-[#005C55]" />Historial firmado y sellado</h2><p class="mt-0.5 text-xs text-[#64748B]">Cada documento conserva el texto, firmante, versión y huella SHA-256 del momento de aceptación.</p></header>
            <div v-if="consents.length" class="divide-y divide-[#E4E7EC]">
                <article v-for="consent in consents" :key="consent.id" class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(220px,1.4fr)_minmax(200px,1fr)_minmax(220px,1fr)_auto] lg:items-center">
                    <div><p class="text-sm font-bold">{{ consent.title }}</p><p class="mt-1 font-mono text-[10px] text-[#64748B]">Versión {{ consent.template_version }} · {{ consent.integrity.algorithm }}</p></div>
                    <div><p class="text-xs font-semibold text-[#344054]">{{ consent.signed_by_name }}</p><p class="mt-1 text-[10px] text-[#64748B]">{{ relationshipLabel(consent.relationship) }} · {{ consent.signed_by_identification || 'Sin identificación' }}</p></div>
                    <div><p class="text-xs text-[#344054]">{{ formatDate(consent.signed_at) }}</p><span class="mt-1 inline-flex items-center gap-1 border px-2 py-1 text-[10px] font-bold" :class="integrityMeta(consent.integrity.status).classes"><CheckCircle2 v-if="consent.integrity.status === 'verified'" class="h-3 w-3" /><ShieldAlert v-else class="h-3 w-3" />{{ integrityMeta(consent.integrity.status).label }}</span></div>
                    <button type="button" class="inline-flex h-9 w-fit items-center gap-2 border border-[#9AAEAA] px-3 text-xs font-semibold text-[#344054] hover:bg-[#F2F4F7]" @click="selectedConsent = consent"><Eye class="h-3.5 w-3.5" />Ver documento</button>
                </article>
            </div>
            <div v-else class="grid min-h-40 place-items-center p-6 text-center"><div><ShieldCheck class="mx-auto h-8 w-8 text-[#98A2B3]" /><p class="mt-2 text-sm font-semibold text-[#344054]">Sin consentimientos firmados</p><p class="mt-1 text-xs text-[#64748B]">Los documentos completados aparecerán aquí con su sello de integridad.</p></div></div>
        </section>

        <div v-if="isSigningModal && selectedTemplate" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-[#0F172A]/50 p-3 backdrop-blur-sm" @click.self="closeSigning">
            <form class="my-4 w-full max-w-5xl border border-[#D8E0DE] bg-white shadow-2xl" @submit.prevent="submitSigning">
                <header class="flex items-start justify-between border-b border-[#D8E0DE] p-4 sm:p-5"><div class="flex gap-3"><div class="grid h-10 w-10 shrink-0 place-items-center bg-[#D8ECE9] text-[#005C55]"><FileSignature class="h-5 w-5" /></div><div><p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Consentimiento informado · v{{ selectedTemplate.version }}</p><h2 class="mt-1 text-lg font-bold">{{ selectedTemplate.title }}</h2></div></div><button type="button" class="p-1 text-[#64748B]" aria-label="Cerrar" @click="closeSigning"><X class="h-5 w-5" /></button></header>
                <div class="grid max-h-[72vh] overflow-y-auto lg:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                    <section class="border-b border-[#D8E0DE] p-4 lg:border-b-0 lg:border-r sm:p-5"><div class="mb-3 flex items-center justify-between"><h3 class="text-xs font-bold uppercase tracking-wider text-[#344054]">Texto que será firmado</h3><span class="font-mono text-[10px] text-[#64748B]">{{ patient.record_number }}</span></div><div class="min-h-80 space-y-3 border border-[#D8E0DE] bg-[#FBFCFC] p-5 text-sm leading-7 text-[#344054]"><template v-for="(block, index) in documentBlocks(renderedTemplate(selectedTemplate))" :key="index"><h4 v-if="block.type === 'heading'" class="text-base font-bold text-[#131B2E]">{{ block.text }}</h4><p v-else-if="block.type === 'bullet'" class="flex gap-2 before:text-[#005C55] before:content-['•']">{{ block.text }}</p><p v-else>{{ block.text }}</p></template></div><div class="mt-3 flex items-start gap-2 border border-[#B2DDFF] bg-[#EFF8FF] p-3 text-[11px] leading-5 text-[#175CD3]"><ShieldCheck class="mt-0.5 h-4 w-4 shrink-0" />La versión y el texto renderizado quedarán congelados dentro del sello de integridad.</div></section>
                    <section class="space-y-4 p-4 sm:p-5">
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1"><label><span class="mb-1 block text-xs font-bold text-[#344054]">Nombre del firmante *</span><input v-model="form.signed_by_name" required maxlength="255" class="h-10 w-full border border-[#9AAEAA] px-3 text-sm focus:border-[#005C55] focus:outline-none" /></label><label><span class="mb-1 block text-xs font-bold text-[#344054]">Documento de identidad *</span><input v-model="form.signed_by_identification" required maxlength="50" class="h-10 w-full border border-[#9AAEAA] px-3 font-mono text-sm focus:border-[#005C55] focus:outline-none" placeholder="Cédula o pasaporte" /></label></div>
                        <label><span class="mb-1 block text-xs font-bold text-[#344054]">Relación con el paciente *</span><select v-model="form.relationship" class="h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm focus:border-[#005C55] focus:outline-none"><option value="patient">Paciente</option><option value="parent">Padre / madre</option><option value="guardian">Tutor</option><option value="legal_representative">Representante legal</option></select></label>
                        <fieldset><div class="mb-1 flex items-center justify-between"><legend class="text-xs font-bold text-[#344054]">Firma manuscrita *</legend><button type="button" class="inline-flex items-center gap-1 text-[10px] font-bold text-[#005C55]" @click="clearSignature"><RotateCcw class="h-3 w-3" />Limpiar</button></div><div class="relative border border-[#9AAEAA] bg-white"><canvas ref="signatureCanvas" class="h-36 w-full touch-none cursor-crosshair" aria-label="Área de firma manuscrita" @pointerdown="startDrawing" @pointermove="draw" @pointerup="finishDrawing" @pointercancel="finishDrawing" @pointerleave="finishDrawing"></canvas><span v-if="!hasSignature" class="pointer-events-none absolute inset-x-0 bottom-3 text-center text-[10px] text-[#98A2B3]">Firma dentro del recuadro</span></div><p v-if="form.errors.signature_data" class="mt-1 text-xs text-[#B42318]">{{ form.errors.signature_data }}</p></fieldset>
                        <label class="flex items-start gap-2 border border-[#D8E0DE] bg-[#F7FAF9] p-3"><input v-model="form.accepted_terms" type="checkbox" class="mt-0.5 h-4 w-4 accent-[#005C55]" /><span class="text-[11px] leading-5 text-[#344054]">Declaro haber leído el documento completo, haber recibido explicación de riesgos, beneficios y alternativas, y firmar voluntariamente.</span></label>
                        <p v-if="form.errors.accepted_terms" class="text-xs text-[#B42318]">{{ form.errors.accepted_terms }}</p><p v-if="form.errors.signed_by_identification || form.errors.relationship" class="text-xs text-[#B42318]">{{ form.errors.signed_by_identification || form.errors.relationship }}</p>
                    </section>
                </div>
                <footer class="flex flex-col-reverse gap-2 border-t border-[#D8E0DE] bg-[#F7FAF9] p-4 sm:flex-row sm:items-center sm:justify-between"><p class="inline-flex items-center gap-1.5 text-[10px] text-[#64748B]"><UserRoundCheck class="h-3.5 w-3.5" />La firma se almacena de forma privada y no se vuelve a exponer en el listado.</p><div class="flex gap-2"><button type="button" class="h-10 border border-[#9AAEAA] bg-white px-4 text-xs font-semibold" @click="closeSigning">Cancelar</button><button type="submit" :disabled="!canSubmit || form.processing" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50"><Check class="h-4 w-4" />{{ form.processing ? 'Sellando…' : 'Firmar y sellar' }}</button></div></footer>
            </form>
        </div>

        <div v-if="selectedConsent" class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-[#0F172A]/50 p-3 backdrop-blur-sm" @click.self="selectedConsent = null">
            <article class="my-4 w-full max-w-3xl border border-[#D8E0DE] bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-[#D8E0DE] p-5"><div><p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B]">Documento firmado · v{{ selectedConsent.template_version }}</p><h2 class="mt-1 text-lg font-bold">{{ selectedConsent.title }}</h2></div><button type="button" aria-label="Cerrar documento" @click="selectedConsent = null"><X class="h-5 w-5 text-[#64748B]" /></button></header><div class="space-y-4 p-5"><div class="space-y-3 border border-[#D8E0DE] bg-[#FBFCFC] p-5 text-sm leading-7 text-[#344054]"><template v-for="(block, index) in documentBlocks(selectedConsent.rendered_content)" :key="index"><h3 v-if="block.type === 'heading'" class="text-base font-bold text-[#131B2E]">{{ block.text }}</h3><p v-else-if="block.type === 'bullet'" class="flex gap-2 before:text-[#005C55] before:content-['•']">{{ block.text }}</p><p v-else>{{ block.text }}</p></template></div><div class="grid gap-3 border border-[#D8E0DE] p-4 sm:grid-cols-2"><div><p class="text-[10px] font-bold uppercase text-[#64748B]">Firmante</p><p class="mt-1 text-sm font-semibold">{{ selectedConsent.signed_by_name }}</p><p class="text-xs text-[#64748B]">{{ relationshipLabel(selectedConsent.relationship) }} · {{ selectedConsent.signed_by_identification }}</p></div><div><p class="text-[10px] font-bold uppercase text-[#64748B]">Fecha de firma</p><p class="mt-1 text-sm font-semibold">{{ formatDate(selectedConsent.signed_at) }}</p><span class="mt-1 inline-flex border px-2 py-1 text-[10px] font-bold" :class="integrityMeta(selectedConsent.integrity.status).classes">{{ integrityMeta(selectedConsent.integrity.status).label }}</span></div></div><div class="border border-[#D8E0DE] bg-[#F7FAF9] p-3"><p class="text-[10px] font-bold uppercase text-[#64748B]">Huella SHA-256</p><p class="mt-1 break-all font-mono text-[10px] leading-4 text-[#344054]">{{ selectedConsent.integrity_hash }}</p></div></div></article>
        </div>
    </main>
</template>
