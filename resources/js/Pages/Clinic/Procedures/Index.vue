<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { DollarSign, Plus, Clock } from 'lucide-vue-next'

interface ProcedureItem {
    id: string
    name: string
    code: string | null
    price: number
    estimated_minutes: number
    requires_lab: boolean
}

interface CategoryItem {
    id: string
    name: string
    color: string
    procedures: ProcedureItem[]
}

const props = defineProps<{
    categories: CategoryItem[]
}>()

const isCreateModal = ref(false)

const form = useForm({
    category_id: props.categories[0]?.id || '',
    code: '',
    name: '',
    description: '',
    price: 0,
    estimated_minutes: 30,
    tax_rate: 0,
    requires_lab: false,
})

function submitProcedure() {
    form.post('/procedures', {
        onSuccess: () => {
            isCreateModal.value = false
            form.reset()
        },
    })
}
</script>

<template>
    <Head title="Arancel de Procedimientos Odontológicos — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <DollarSign class="w-6 h-6 text-teal-400" /> Arancel de Procedimientos & Tarifas
                    </h1>
                    <p class="text-sm text-slate-400">Catálogo de prestaciones, tiempos clínicos y precios base</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-lg text-sm transition"
                        @click="isCreateModal = true"
                    >
                        <Plus class="w-4 h-4" /> Nuevo Procedimiento
                    </button>
                </div>
            </div>

            <!-- Categories and Procedures Grid -->
            <div class="space-y-6">
                <div v-for="cat in categories" :key="cat.id" class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color }"></span>
                        <h2 class="text-base font-bold text-white">{{ cat.name }} ({{ cat.procedures.length }})</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div v-for="proc in cat.procedures" :key="proc.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl flex items-center justify-between">
                            <div>
                                <div class="font-bold text-white text-sm">{{ proc.name }}</div>
                                <div class="text-xs text-slate-500 flex items-center gap-2 mt-1">
                                    <span v-if="proc.code" class="font-mono text-teal-400">{{ proc.code }}</span>
                                    <span class="flex items-center gap-1"><Clock class="w-3 h-3" /> {{ proc.estimated_minutes }}m</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-base font-black text-teal-400 font-mono">${{ proc.price.toFixed(2) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for New Procedure -->
            <div v-if="isCreateModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Nuevo Procedimiento en Arancel</h2>
                    <button class="text-slate-400 hover:text-white" @click="isCreateModal = false">×</button>
                </div>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitProcedure">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Categoría</label>
                        <select v-model="form.category_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Código Interno</label>
                        <input v-model="form.code" type="text" placeholder="Ej. REST-01" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nombre del Procedimiento *</label>
                        <input v-model="form.name" type="text" required placeholder="Ej. Restauración en resina 1 superficie" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Precio Base ($) *</label>
                        <input v-model.number="form.price" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Duración Estimada (min)</label>
                        <input v-model.number="form.estimated_minutes" type="number" min="5" max="480" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isCreateModal = false">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Guardar Procedimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>