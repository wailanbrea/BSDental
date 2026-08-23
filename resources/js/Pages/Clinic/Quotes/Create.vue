<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ArrowLeft, FilePlus2, Plus, ReceiptText, Stethoscope, Trash2 } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface PatientDetails { id: string; record_number: string; full_name: string }
interface ProfessionalDetails { id: string; full_name: string }
interface ProcedureItem { id: string; code: string | null; name: string; price: number }
interface CategoryItem { id: string; name: string; procedures: ProcedureItem[] }
interface FormItem { procedure_id: string; tooth_number: number | null; surface: string; quantity: number; discount_percentage: number }

const props = defineProps<{ patient: PatientDetails | null; mode?: 'patient' | 'prospect'; professionals: ProfessionalDetails[]; categories: CategoryItem[]; suggestedNumber: string }>()
const isProspect = computed(() => props.mode === 'prospect' || !props.patient)
const backUrl = computed(() => isProspect.value ? '/quotes' : `/patients/${props.patient!.id}/quotes`)
const form = useForm({
    prospect_first_name: '', prospect_last_name: '', prospect_phone: '', prospect_email: '',
    professional_id: props.professionals[0]?.id || '', alternative_name: 'Plan principal', notes: '', items: [] as FormItem[],
})
const selectedProcId = ref('')
const selectedTooth = ref<number | null>(null)
const selectedSurface = ref('all')
const validTeeth = [
    ...[1, 2, 3, 4].flatMap(quadrant => Array.from({ length: 8 }, (_, index) => quadrant * 10 + index + 1)),
    ...[5, 6, 7, 8].flatMap(quadrant => Array.from({ length: 5 }, (_, index) => quadrant * 10 + index + 1)),
]
const surfaces = [
    { value: 'all', label: 'Todas / general' }, { value: 'vestibular', label: 'Vestibular' },
    { value: 'lingual_palatal', label: 'Lingual / palatina' }, { value: 'mesial', label: 'Mesial' },
    { value: 'distal', label: 'Distal' }, { value: 'occlusal_incisal', label: 'Oclusal / incisal' },
]

function findProcedure(id: string) { return props.categories.flatMap(category => category.procedures).find(procedure => procedure.id === id) }
function addItem() {
    if (!selectedProcId.value) return
    form.items.push({ procedure_id: selectedProcId.value, tooth_number: selectedTooth.value, surface: selectedSurface.value, quantity: 1, discount_percentage: 0 })
    selectedProcId.value = ''
    selectedTooth.value = null
    selectedSurface.value = 'all'
}
function removeItem(index: number) { form.items.splice(index, 1) }
function lineTotal(item: FormItem) { return (findProcedure(item.procedure_id)?.price || 0) * item.quantity * (1 - item.discount_percentage / 100) }
const totalEstimated = computed(() => form.items.reduce((sum, item) => sum + lineTotal(item), 0))
const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0)
function submit() { form.post(isProspect.value ? '/quotes/quick' : `/patients/${props.patient!.id}/quotes`) }
</script>

<template>
    <Head :title="isProspect ? 'Cotización rápida' : `Nuevo presupuesto — ${patient!.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1400px] space-y-5 p-4 md:p-7">
            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 md:flex-row md:items-end">
                <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Presupuestos</p><h1 class="mt-1 text-2xl font-bold text-[#131B2E]">{{ isProspect ? 'Cotización rápida para prospecto' : 'Nueva alternativa de tratamiento' }}</h1><p class="mt-1 text-sm text-[#667085]">{{ patient ? `${patient.full_name} · ${patient.record_number} · ` : 'Sin historia clínica todavía · ' }}<span class="font-mono">{{ suggestedNumber }}</span></p></div>
                <Link :href="backUrl" class="inline-flex h-10 items-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]"><ArrowLeft class="h-4 w-4" /> Presupuestos</Link>
            </header>

            <form class="grid gap-5 xl:grid-cols-[1fr_340px]" @submit.prevent="submit">
                <div class="space-y-5">
                    <section v-if="isProspect" class="border border-[#B7D9D4] bg-[#F1FAF8] p-5">
                        <div><h2 class="flex items-center gap-2 font-semibold text-[#131B2E]"><FilePlus2 class="h-5 w-5 text-[#006B63]" /> Datos del prospecto</h2><p class="mt-1 text-sm text-[#52615E]">Solo se crea la cotización. La historia clínica se abrirá cuando decidas convertirlo en paciente.</p></div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-semibold text-[#455653]">Nombre *<input v-model="form.prospect_first_name" required maxlength="100" autocomplete="given-name" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm" /></label>
                            <label class="text-sm font-semibold text-[#455653]">Apellido *<input v-model="form.prospect_last_name" required maxlength="100" autocomplete="family-name" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm" /></label>
                            <label class="text-sm font-semibold text-[#455653]">Teléfono<input v-model="form.prospect_phone" maxlength="50" autocomplete="tel" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm" placeholder="Teléfono o correo requerido" /></label>
                            <label class="text-sm font-semibold text-[#455653]">Correo<input v-model="form.prospect_email" type="email" maxlength="255" autocomplete="email" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm" placeholder="Teléfono o correo requerido" /></label>
                        </div>
                    </section>
                    <section class="border border-[#D8E0DE] bg-white p-5">
                        <h2 class="flex items-center gap-2 font-semibold text-[#131B2E]"><ReceiptText class="h-5 w-5 text-[#006B63]" /> Datos del presupuesto</h2>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="text-sm font-semibold text-[#455653]">Nombre de la alternativa *<input v-model="form.alternative_name" required maxlength="255" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm text-[#131B2E] outline-none focus:border-[#007D73]" /></label>
                            <label class="text-sm font-semibold text-[#455653]">Profesional tratante<select v-model="form.professional_id" class="mt-1.5 h-11 w-full border border-[#9AAEAA] bg-white px-3 text-sm text-[#131B2E] outline-none focus:border-[#007D73]"><option value="">Sin profesional asignado</option><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select></label>
                        </div>
                    </section>

                    <section class="border border-[#D8E0DE] bg-white p-5">
                        <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold text-[#131B2E]">Procedimientos</h2><p class="mt-1 text-sm text-[#667085]">Añade prestaciones desde el catálogo vigente.</p></div><span class="bg-[#D8ECE9] px-2 py-1 font-mono text-xs font-bold text-[#006B63]">{{ form.items.length }} ítems</span></div>
                        <div class="mt-4 grid gap-3 border border-[#D8E0DE] bg-[#F8FAFC] p-4 lg:grid-cols-[2fr_110px_1.4fr_auto] lg:items-end">
                            <label class="text-xs font-bold uppercase tracking-[0.08em] text-[#455653]">Procedimiento<select v-model="selectedProcId" class="mt-1.5 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm font-normal normal-case tracking-normal text-[#131B2E]"><option value="">Seleccionar…</option><optgroup v-for="category in categories" :key="category.id" :label="category.name"><option v-for="procedure in category.procedures" :key="procedure.id" :value="procedure.id">{{ procedure.code || 'S/C' }} · {{ procedure.name }} · {{ money(procedure.price) }}</option></optgroup></select></label>
                            <label class="text-xs font-bold uppercase tracking-[0.08em] text-[#455653]">Pieza FDI<select v-model="selectedTooth" class="mt-1.5 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm font-normal text-[#131B2E]"><option :value="null">General</option><option v-for="tooth in validTeeth" :key="tooth" :value="tooth">{{ tooth }}</option></select></label>
                            <label class="text-xs font-bold uppercase tracking-[0.08em] text-[#455653]">Superficie<select v-model="selectedSurface" class="mt-1.5 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm font-normal normal-case tracking-normal text-[#131B2E]"><option v-for="surface in surfaces" :key="surface.value" :value="surface.value">{{ surface.label }}</option></select></label>
                            <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white disabled:opacity-50" :disabled="!selectedProcId" @click="addItem"><Plus class="h-4 w-4" /> Añadir</button>
                        </div>

                        <div v-if="form.items.length" class="mt-4 overflow-x-auto border border-[#D8E0DE]">
                            <table class="w-full min-w-[760px] text-left text-sm"><thead class="bg-[#F2F6F5] text-xs font-bold uppercase tracking-[0.08em] text-[#455653]"><tr><th class="px-3 py-3">Procedimiento</th><th class="px-3 py-3">Pieza</th><th class="px-3 py-3 text-center">Cantidad</th><th class="px-3 py-3 text-center">Descuento</th><th class="px-3 py-3 text-right">Total</th><th class="w-12"></th></tr></thead><tbody class="divide-y divide-[#E2E8F0]"><tr v-for="(item, index) in form.items" :key="`${item.procedure_id}-${index}`"><td class="px-3 py-3"><p class="font-semibold text-[#131B2E]">{{ findProcedure(item.procedure_id)?.name }}</p><p class="font-mono text-xs text-[#667085]">{{ money(findProcedure(item.procedure_id)?.price || 0) }}</p></td><td class="px-3 py-3 font-mono text-[#006B63]">{{ item.tooth_number || 'General' }}</td><td class="px-3 py-3 text-center"><input v-model.number="item.quantity" type="number" min="1" max="99" class="h-9 w-16 border border-[#9AAEAA] text-center" /></td><td class="px-3 py-3 text-center"><input v-model.number="item.discount_percentage" type="number" min="0" max="100" class="h-9 w-16 border border-[#9AAEAA] text-center" />%</td><td class="px-3 py-3 text-right font-mono font-bold">{{ money(lineTotal(item)) }}</td><td><button type="button" class="p-2 text-red-700" aria-label="Eliminar procedimiento" @click="removeItem(index)"><Trash2 class="h-4 w-4" /></button></td></tr></tbody></table>
                        </div>
                        <div v-else class="mt-4 border border-dashed border-[#9AAEAA] p-8 text-center text-sm text-[#667085]">Selecciona un procedimiento para comenzar el presupuesto.</div>
                    </section>

                    <section class="border border-[#D8E0DE] bg-white p-5"><label class="text-sm font-semibold text-[#455653]">Notas clínicas y administrativas<textarea v-model="form.notes" rows="4" maxlength="2000" class="mt-1.5 w-full resize-y border border-[#9AAEAA] p-3 text-sm text-[#131B2E] outline-none focus:border-[#007D73]" placeholder="Vigencia, consideraciones o indicaciones para el paciente…"></textarea></label></section>
                </div>

                <aside class="h-fit border border-[#BDC9C6] bg-white p-5 xl:sticky xl:top-24">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-[#667085]">Resumen estimado</p><p class="mt-2 font-mono text-3xl font-bold text-[#005C55]">{{ money(totalEstimated) }}</p><p class="mt-1 text-sm text-[#667085]">{{ form.items.length }} procedimientos incluidos</p>
                    <div class="mt-5 space-y-2 border-t border-[#D8E0DE] pt-4 text-sm"><p class="flex items-center gap-2 text-[#455653]"><Stethoscope class="h-4 w-4 text-[#006B63]" /> {{ professionals.find(p => p.id === form.professional_id)?.full_name || 'Sin profesional asignado' }}</p><p class="text-xs leading-5 text-[#667085]">Los precios se fijarán al guardar este presupuesto para conservar su evidencia histórica.</p></div>
                    <button type="submit" :disabled="form.processing || !form.items.length" class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"><FilePlus2 class="h-4 w-4" /> Guardar presupuesto</button>
                    <Link :href="backUrl" class="mt-2 inline-flex h-10 w-full items-center justify-center text-sm font-semibold text-[#005C55]">Cancelar</Link>
                </aside>
            </form>
        </div>
    </ClinicLayout>
</template>
