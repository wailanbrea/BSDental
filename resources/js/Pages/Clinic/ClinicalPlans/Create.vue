<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ArrowLeft, ClipboardPlus, Plus, Trash2 } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface Procedure { id: string; code: string | null; name: string; estimated_minutes: number }
interface Category { id: string; name: string; procedures: Procedure[] }
interface Item { procedure_id: string; tooth_number: number | null; surface: string; quantity: number; clinical_note: string; priority: string; estimated_minutes: number | null; status: string }
interface Encounter { id: string; patient: { full_name: string; record_number: string }; diagnoses: { id: string; code: string | null; description: string }[] }

const props = defineProps<{ encounter: Encounter; categories: Category[] }>()
const form = useForm({ clinical_diagnosis_id: '', title: 'Plan clínico propuesto', notes: '', items: [] as Item[] })
const procedureId = ref('')
const teeth = [...[1, 2, 3, 4].flatMap(q => Array.from({ length: 8 }, (_, i) => q * 10 + i + 1)), ...[5, 6, 7, 8].flatMap(q => Array.from({ length: 5 }, (_, i) => q * 10 + i + 1))]
const procedures = computed(() => props.categories.flatMap(category => category.procedures))

function addItem() {
    const procedure = procedures.value.find(item => item.id === procedureId.value)
    if (!procedure) return
    form.items.push({ procedure_id: procedure.id, tooth_number: null, surface: 'all', quantity: 1, clinical_note: '', priority: 'normal', estimated_minutes: procedure.estimated_minutes, status: 'proposed' })
    procedureId.value = ''
}

function procedure(item: Item) { return procedures.value.find(candidate => candidate.id === item.procedure_id) }
function submit() { form.post(appUrl(`/encounters/${props.encounter.id}/clinical-plans`)) }
</script>

<template>
    <Head :title="`Plan clínico — ${encounter.patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-col justify-between gap-3 border-b border-[#D8E0DE] pb-5 md:flex-row md:items-end"><div><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Propuesta clínica</p><h1 class="mt-1 text-2xl font-bold text-[#131B2E]">Nuevo plan clínico</h1><p class="mt-1 text-sm text-[#667085]">{{ encounter.patient.full_name }} · {{ encounter.patient.record_number }} · independiente del diagnóstico y de la cotización.</p></div><Link :href="appUrl(`/encounters/${encounter.id}`)" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ArrowLeft class="h-4 w-4" /> Encuentro</Link></header>
            <form class="grid gap-5 xl:grid-cols-[1fr_320px]" @submit.prevent="submit">
                <div class="space-y-5">
                    <section class="border border-[#D8E0DE] bg-white p-5"><div class="grid gap-4 md:grid-cols-2"><label class="text-sm font-semibold text-[#455653]">Nombre del plan *<input v-model="form.title" required maxlength="255" class="mt-1.5 h-10 w-full border border-[#9AAEAA] px-3 font-normal" /></label><label class="text-sm font-semibold text-[#455653]">Diagnóstico de origen<select v-model="form.clinical_diagnosis_id" class="mt-1.5 h-10 w-full border border-[#9AAEAA] bg-white px-3 font-normal"><option value="">Sin diagnóstico específico</option><option v-for="diagnosis in encounter.diagnoses" :key="diagnosis.id" :value="diagnosis.id">{{ diagnosis.code || 'S/C' }} · {{ diagnosis.description }}</option></select></label><label class="md:col-span-2 text-sm font-semibold text-[#455653]">Razonamiento general<textarea v-model="form.notes" maxlength="2000" rows="3" class="mt-1.5 w-full border border-[#9AAEAA] p-3 font-normal" /></label></div></section>
                    <section class="border border-[#D8E0DE] bg-white p-5"><div class="flex flex-wrap items-end gap-3"><label class="min-w-64 flex-1 text-xs font-bold uppercase tracking-wide text-[#455653]">Procedimiento<select v-model="procedureId" class="mt-1.5 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm font-normal normal-case"><option value="">Seleccionar...</option><optgroup v-for="category in categories" :key="category.id" :label="category.name"><option v-for="candidate in category.procedures" :key="candidate.id" :value="candidate.id">{{ candidate.code || 'S/C' }} · {{ candidate.name }}</option></optgroup></select></label><button type="button" :disabled="!procedureId" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50" @click="addItem"><Plus class="h-4 w-4" /> Añadir</button></div>
                        <div v-if="form.items.length" class="mt-5 space-y-4"><article v-for="(item, index) in form.items" :key="`${item.procedure_id}-${index}`" class="border border-[#D8E0DE] p-4"><div class="flex justify-between gap-3"><div><p class="font-semibold text-[#131B2E]">{{ procedure(item)?.name }}</p><p class="text-xs text-[#667085]">{{ procedure(item)?.code || 'Sin código' }}</p></div><button type="button" class="text-[#B42318]" @click="form.items.splice(index, 1)"><Trash2 class="h-4 w-4" /></button></div><div class="mt-4 grid gap-3 md:grid-cols-4"><label class="text-xs font-semibold text-[#455653]">Pieza<select v-model="item.tooth_number" class="mt-1 h-9 w-full border border-[#9AAEAA] bg-white px-2"><option :value="null">General</option><option v-for="tooth in teeth" :key="tooth" :value="tooth">{{ tooth }}</option></select></label><label class="text-xs font-semibold text-[#455653]">Cantidad<input v-model.number="item.quantity" min="1" max="99" type="number" class="mt-1 h-9 w-full border border-[#9AAEAA] px-2" /></label><label class="text-xs font-semibold text-[#455653]">Prioridad<select v-model="item.priority" class="mt-1 h-9 w-full border border-[#9AAEAA] bg-white px-2"><option value="low">Baja</option><option value="normal">Normal</option><option value="high">Alta</option><option value="urgent">Urgente</option></select></label><label class="text-xs font-semibold text-[#455653]">Duración (min)<input v-model.number="item.estimated_minutes" min="1" max="1440" type="number" class="mt-1 h-9 w-full border border-[#9AAEAA] px-2" /></label><label class="md:col-span-4 text-xs font-semibold text-[#455653]">Nota o justificación<textarea v-model="item.clinical_note" maxlength="2000" rows="2" class="mt-1 w-full border border-[#9AAEAA] p-2" /></label></div></article></div><p v-else class="mt-5 border border-dashed border-[#9AAEAA] p-8 text-center text-sm text-[#667085]">Añade procedimientos desde el catálogo para construir el plan.</p></section>
                </div>
                <aside class="h-fit border border-[#BDC9C6] bg-white p-5 xl:sticky xl:top-24"><ClipboardPlus class="h-6 w-6 text-[#006B63]" /><h2 class="mt-3 font-bold text-[#131B2E]">Propuesta no ejecutable</h2><p class="mt-2 text-sm leading-6 text-[#667085]">El plan conserva la indicación clínica. Solo los ítems seleccionados y cotizados podrán generar tratamiento después de aprobar la cotización.</p><button :disabled="form.processing || !form.items.length" class="mt-5 inline-flex h-11 w-full items-center justify-center bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50">Guardar plan clínico</button></aside>
            </form>
        </div>
    </ClinicLayout>
</template>
