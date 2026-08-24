<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Clock, Plus, Tag, X } from 'lucide-vue-next'

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

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

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
    <ClinicLayout>
        <Head title="Arancel de Procedimientos Odontológicos — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <Tag class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Arancel de Procedimientos & Tarifas
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Catálogo de prestaciones odontológicas, tiempos de sillón y precios base por categoría
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isCreateModal = true"
                    >
                        <Plus class="w-3.5 h-3.5" /> Nuevo Procedimiento
                    </button>
                </div>
            </div>

            <!-- Categories and Procedures Grid -->
            <div class="space-y-6">
                <div 
                    v-for="cat in categories" 
                    :key="cat.id" 
                    class="bg-white border border-[#E2E8F0] rounded-xl shadow-xs p-5 space-y-4"
                >
                    <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: cat.color || '#005C55' }"></span>
                            <h2 class="font-bold text-sm text-[#131B2E]">{{ cat.name }}</h2>
                        </div>
                        <span class="text-xs font-mono text-[#505F76]">{{ cat.procedures.length }} prestaciones</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div 
                            v-for="proc in cat.procedures" 
                            :key="proc.id" 
                            class="p-3.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl flex items-center justify-between hover:border-[#BDC9C6] transition"
                        >
                            <div>
                                <div class="font-bold text-[#131B2E] text-xs">{{ proc.name }}</div>
                                <div class="text-[11px] text-[#505F76] flex items-center gap-2 mt-1">
                                    <span v-if="proc.code" class="font-mono text-[#005C55] font-semibold">{{ proc.code }}</span>
                                    <span class="flex items-center gap-1"><Clock class="w-3 h-3 text-[#505F76]" /> {{ proc.estimated_minutes }}m</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-[#005C55] font-data-tabular">
                                    {{ formatMoney(proc.price) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal for New Procedure -->
            <div v-if="isCreateModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isCreateModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Plus class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Registrar Nuevo Procedimiento en Arancel</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isCreateModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitProcedure">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Categoría *</label>
                            <select v-model="form.category_id" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Código Interno</label>
                            <input v-model="form.code" type="text" placeholder="Ej. REST-01" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombre del Procedimiento *</label>
                            <input v-model="form.name" type="text" required placeholder="Ej. Restauración en resina 1 superficie" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Precio Base ($) *</label>
                            <input v-model.number="form.price" type="number" step="0.01" min="0" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Duración Estimada (min)</label>
                            <input v-model.number="form.estimated_minutes" type="number" min="5" max="480" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isCreateModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Guardar Procedimiento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
