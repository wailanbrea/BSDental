<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { FileSignature, ArrowLeft, CheckCircle2, ShieldCheck } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface TemplateItem {
    id: string
    title: string
    slug: string
    version: number
    content: string
}

interface ConsentItem {
    id: string
    title: string
    template_version: number
    signed_by_name: string
    relationship: string
    signed_at: string
    integrity_hash: string
    rendered_content: string
}

const props = defineProps<{
    patient: PatientDetails
    templates: TemplateItem[]
    consents: ConsentItem[]
}>()

const isSigningModal = ref(false)
const selectedTemplate = ref<TemplateItem | null>(null)

const form = useForm({
    consent_template_id: '',
    signed_by_name: props.patient.full_name,
    signed_by_identification: '',
    relationship: 'patient',
    signature_type: 'drawn',
    signature_data: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAiIGhlaWdodD0iNDAiPjxwYXRoIGQ9Ik0xMCAyMCBRIDUwIDUgOTAgMjAiIHN0cm9rZT0iYmxhY2siIGZpbGw9InRyYW5zcGFyZW50Ii8+PC9zdmc+',
})

function openSigning(template: TemplateItem) {
    selectedTemplate.value = template
    form.consent_template_id = template.id
    isSigningModal.value = true
}

function submitSigning() {
    form.post(`/patients/${props.patient.id}/consents`, {
        onSuccess: () => {
            isSigningModal.value = false
            form.reset()
        },
    })
}
</script>

<template>
    <Head :title="`Consentimientos Informados — ${patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <FileSignature class="w-6 h-6 text-teal-400" /> Consentimientos Informados Digitales
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

            <!-- Templates List to Sign -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Plantillas Disponibles para Firma</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="tpl in templates" :key="tpl.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex items-center justify-between">
                        <div>
                            <div class="font-bold text-white text-sm">{{ tpl.title }}</div>
                            <div class="text-xs text-slate-500 font-mono">Versión v{{ tpl.version }}</div>
                        </div>
                        <button
                            class="px-3 py-1.5 bg-teal-500 hover:bg-teal-400 text-slate-950 text-xs font-bold rounded-lg transition"
                            @click="openSigning(tpl)"
                        >
                            Firmar Documento
                        </button>
                    </div>
                </div>
            </div>

            <!-- Signed Consents History -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                    <ShieldCheck class="w-4 h-4" /> Consentimientos Firmados & Sellados
                </h2>

                <div v-if="consents.length === 0" class="text-xs text-slate-500 py-6 text-center">
                    No se han registrado consentimientos informados firmados para este paciente.
                </div>

                <div class="space-y-3">
                    <div v-for="c in consents" :key="c.id" class="p-5 bg-slate-900/90 border border-emerald-500/30 rounded-2xl space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-sm">{{ c.title }} (v{{ c.template_version }})</span>
                            <span class="text-emerald-400 font-semibold flex items-center gap-1">
                                <CheckCircle2 class="w-4 h-4" /> Sellado & Válido
                            </span>
                        </div>
                        <p class="text-slate-400">
                            Firmado por: <span class="text-slate-200 font-semibold">{{ c.signed_by_name }}</span> ({{ c.relationship }}) el {{ c.signed_at }}
                        </p>
                        <div class="text-[11px] font-mono text-slate-500">Hash SHA256: {{ c.integrity_hash }}</div>
                    </div>
                </div>
            </div>

            <!-- Signing Modal -->
            <div v-if="isSigningModal && selectedTemplate" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Firma de Consentimiento: {{ selectedTemplate.title }}</h2>
                    <button class="text-slate-400 hover:text-white" @click="isSigningModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitSigning">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Nombre Completo del Firmante</label>
                            <input v-model="form.signed_by_name" type="text" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Documento de Identidad</label>
                            <input v-model="form.signed_by_identification" type="text" placeholder="Ej. V-12345678" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        </div>
                    </div>

                    <div class="p-4 bg-slate-900 border border-slate-700 rounded-xl text-xs space-y-2">
                        <span class="text-slate-400 font-semibold block">Texto del Consentimiento:</span>
                        <p class="text-slate-300 leading-relaxed">{{ selectedTemplate.content }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isSigningModal = false">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Firmar y Sellar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>