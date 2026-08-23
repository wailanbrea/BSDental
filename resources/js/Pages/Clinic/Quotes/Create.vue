<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { DollarSign, ArrowLeft, Plus, Trash2 } from 'lucide-vue-next'

interface PatientDetails {
    id: string
    record_number: string
    full_name: string
}

interface ProfessionalDetails {
    id: string
    full_name: string
}

interface ProcedureItem {
    id: string
    name: string
    price: number
}

interface CategoryItem {
    id: string
    name: string
    procedures: ProcedureItem[]
}

const props = defineProps<{
    patient: PatientDetails
    professionals: ProfessionalDetails[]
    categories: CategoryItem[]
    suggestedNumber: string
}>()

interface FormItem {
    procedure_id: string
    tooth_number: number | null
    surface: string
    quantity: number
    discount_percentage: number
}

const form = useForm({
    professional_id: props.professionals[0]?.id || '',
    alternative_name: 'Plan Principal',
    notes: '',
    items: [] as FormItem[],
})

const selectedProcId = ref('')
const selectedTooth = ref<number | null>(null)
const selectedSurface = ref('all')

function addItem() {
    if (!selectedProcId.value) return

    form.items.push({
        procedure_id: selectedProcId.value,
        tooth_number: selectedTooth.value,
        surface: selectedSurface.value,
        quantity: 1,
        discount_percentage: 0,
    })

    selectedTooth.value = null
    selectedSurface.value = 'all'
}

function removeItem(index: number) {
    form.items.splice(index, 1)
}

function getProcedureName(procId: string) {
    for (const cat of props.categories) {
        const found = cat.procedures.find(p => p.id === procId)
        if (found) return found.name
    }
    return 'Procedimiento'
}

function getProcedurePrice(procId: string) {
    for (const cat of props.categories) {
        const found = cat.procedures.find(p => p.id === procId)
        if (found) return found.price
    }
    return 0
}

const totalEstimated = computed(() => {
    return form.items.reduce((sum, item) => {
        const p = getProcedurePrice(item.procedure_id)
        const sub = (p * item.quantity) * (1 - (item.discount_percentage / 100))
        return sum + sub
    }, 0)
})

function submit() {
    form.post(`/patients/${props.patient.id}/quotes`)
}
</script>

<template>
    <Head :title="`Crear Presupuesto — ${patient.full_name}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <DollarSign class="w-6 h-6 text-teal-400" /> Nuevo Presupuesto / Cotización
                    </h1>
                    <p class="text-sm text-slate-400">
                        Paciente: <span class="text-white font-semibold">{{ patient.full_name }}</span> ({{ patient.record_number }}) | 
                        Nº Sugerido: <span class="font-mono text-teal-400">{{ suggestedNumber }}</span>
                    </p>
                </div>

                <a :href="`/patients/${patient.id}`" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                    <ArrowLeft class="w-4 h-4" /> Volver a Ficha 360
                </a>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- General Info -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Nombre de la Alternativa / Plan *</label>
                            <input v-model="form.alternative_name" type="text" required placeholder="Ej. Plan Integral / Plan Alternativo B" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Odontólogo / Profesional Tratante</label>
                            <select v-model="form.professional_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                                <option value="">Sin profesional asignado</option>
                                <option v-for="p in professionals" :key="p.id" :value="p.id">Dr(a). {{ p.full_name }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Add Items Form -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Agregar Prestaciones al Presupuesto</h2>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-slate-400 mb-1">Procedimiento del Arancel</label>
                            <select v-model="selectedProcId" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                                <option value="">Seleccione un procedimiento...</option>
                                <optgroup v-for="cat in categories" :key="cat.id" :label="cat.name">
                                    <option v-for="pr in cat.procedures" :key="pr.id" :value="pr.id">{{ pr.name }} (${{ pr.price.toFixed(2) }})</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Pieza FDI (opcional)</label>
                            <input v-model.number="selectedTooth" type="number" min="11" max="85" placeholder="Ej. 16" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                        </div>
                        <div>
                            <button type="button" class="w-full py-2 bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/30 font-bold rounded-lg text-xs flex items-center justify-center gap-1 transition" @click="addItem">
                                <Plus class="w-4 h-4" /> Agregar
                            </button>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div v-if="form.items.length > 0" class="space-y-2 pt-4">
                        <div v-for="(it, idx) in form.items" :key="idx" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-xl flex items-center justify-between text-xs">
                            <div class="flex items-center gap-3">
                                <span v-if="it.tooth_number" class="px-2 py-1 bg-teal-500/10 text-teal-400 font-mono font-bold rounded border border-teal-500/20">
                                    Pz {{ it.tooth_number }}
                                </span>
                                <div>
                                    <div class="font-bold text-white">{{ getProcedureName(it.procedure_id) }}</div>
                                    <div class="text-slate-500">Precio base: ${{ getProcedurePrice(it.procedure_id).toFixed(2) }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div>
                                    <label class="block text-[10px] text-slate-500">Cant.</label>
                                    <input v-model.number="it.quantity" type="number" min="1" class="w-14 px-2 py-1 bg-slate-800 border border-slate-700 rounded text-center text-white text-xs" />
                                </div>
                                <div>
                                    <label class="block text-[10px] text-slate-500">Desc %</label>
                                    <input v-model.number="it.discount_percentage" type="number" min="0" max="100" class="w-14 px-2 py-1 bg-slate-800 border border-slate-700 rounded text-center text-white text-xs" />
                                </div>
                                <div class="text-right w-24">
                                    <div class="font-mono font-bold text-teal-400 text-sm">
                                        ${{ ((getProcedurePrice(it.procedure_id) * it.quantity) * (1 - (it.discount_percentage / 100))).toFixed(2) }}
                                    </div>
                                </div>
                                <button type="button" class="text-rose-400 hover:text-rose-300" @click="removeItem(idx)">
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total & Submit -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 uppercase tracking-wider">Total Estimado del Presupuesto</div>
                        <div class="text-3xl font-black text-teal-400 font-mono">${{ totalEstimated.toFixed(2) }}</div>
                    </div>

                    <div class="flex gap-3">
                        <a :href="`/patients/${patient.id}`" class="px-6 py-2.5 bg-slate-800 text-slate-300 text-sm font-semibold rounded-xl hover:bg-slate-700 transition">Cancelar</a>
                        <button
                            type="submit"
                            :disabled="form.processing || form.items.length === 0"
                            class="px-6 py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 text-sm font-bold rounded-xl shadow-lg shadow-teal-500/20 transition disabled:opacity-50"
                        >
                            Guardar Presupuesto
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>