<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { UserCheck, Plus, Trash2, Phone, Mail, X, Stethoscope, Building } from 'lucide-vue-next'

interface Specialty {
    id: string
    name: string
    code: string | null
}

interface Branch {
    id: string
    name: string
}

interface Professional {
    id: string
    first_name: string
    last_name: string
    full_name: string
    license_number: string | null
    color: string
    phone: string | null
    email: string | null
    is_active: boolean
    specialties: Specialty[]
    branches: Branch[]
}

const props = defineProps<{
    professionals: Professional[]
    specialties: Specialty[]
    branches: Branch[]
}>()

const isCreating = ref(false)

const form = useForm({
    first_name: '',
    last_name: '',
    license_number: '',
    color: '#005C55',
    phone: '',
    email: '',
    specialty_ids: [] as string[],
    branch_ids: [] as string[],
})

function submitCreate() {
    form.post('/professionals', {
        onSuccess: () => {
            form.reset()
            isCreating.value = false
        },
    })
}

function deleteProfessional(pro: Professional) {
    if (confirm(`¿Eliminar al profesional Dr(a). ${pro.full_name}?`)) {
        useForm({}).delete(`/professionals/${pro.id}`)
    }
}
</script>

<template>
    <ClinicLayout>
        <Head title="Equipo Médico y Especialistas — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <UserCheck class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Equipo Médico y Especialistas
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Directorio de odontólogos, números de colegiatura, especialidades y sedes asignadas
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isCreating = true"
                    >
                        <Plus class="w-3.5 h-3.5" /> Nuevo Profesional
                    </button>
                </div>
            </div>

            <!-- Professionals Listing -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div 
                    v-for="pro in props.professionals" 
                    :key="pro.id" 
                    class="bg-white border border-[#E2E8F0] rounded-xl shadow-xs p-5 space-y-4 hover:border-[#BDC9C6] transition flex flex-col justify-between"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div 
                                    class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shadow-2xs text-xs" 
                                    :style="{ backgroundColor: pro.color || '#005C55' }"
                                >
                                    {{ pro.first_name[0] }}{{ pro.last_name[0] }}
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-[#131B2E]">Dr(a). {{ pro.full_name }}</h3>
                                    <p v-if="pro.license_number" class="text-xs text-[#505F76] font-mono">Col. {{ pro.license_number }}</p>
                                </div>
                            </div>
                            <button class="p-1.5 text-[#505F76] hover:text-[#BA1A1A] transition" title="Eliminar profesional" @click="deleteProfessional(pro)">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="text-xs text-[#505F76] space-y-1.5 bg-[#F8FAFC] p-3 rounded-lg border border-[#E2E8F0]">
                            <p v-if="pro.phone" class="flex items-center gap-2 text-[#131B2E]">
                                <Phone class="w-3.5 h-3.5 text-[#505F76]" /> {{ pro.phone }}
                            </p>
                            <p v-if="pro.email" class="flex items-center gap-2">
                                <Mail class="w-3.5 h-3.5 text-[#505F76]" /> {{ pro.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Specialties & Branches -->
                    <div class="space-y-2 border-t border-[#E2E8F0] pt-3">
                        <span class="text-[10px] font-bold text-[#505F76] uppercase tracking-wider block">Especialidades</span>
                        <div class="flex flex-wrap gap-1.5">
                            <span 
                                v-for="spec in pro.specialties" 
                                :key="spec.id" 
                                class="px-2 py-0.5 text-xs bg-[#005C55]/10 text-[#005C55] border border-[#005C55]/20 rounded-md font-medium"
                            >
                                {{ spec.name }}
                            </span>
                            <span v-if="!pro.specialties?.length" class="text-xs text-[#505F76] italic">
                                Sin especialidad registrada
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Modal -->
            <div v-if="isCreating" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isCreating = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Plus class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Registrar Odontólogo / Especialista</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isCreating = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitCreate">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombres *</label>
                            <input v-model="form.first_name" type="text" required placeholder="Ej. Carlos" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Apellidos *</label>
                            <input v-model="form.last_name" type="text" required placeholder="Ej. Mendoza" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Número de Colegiatura / Matrícula</label>
                            <input v-model="form.license_number" type="text" placeholder="Ej. COL-8921" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Color Identificador en Agenda</label>
                            <input v-model="form.color" type="color" class="w-full h-9 p-1 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg cursor-pointer" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Teléfono</label>
                            <input v-model="form.phone" type="text" placeholder="+58 412 123-4567" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Correo Electrónico</label>
                            <input v-model="form.email" type="email" placeholder="carlos.mendoza@clinica.com" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <!-- Especialidades Checkboxes -->
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block text-xs font-semibold text-[#505F76]">Especialidades</label>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <label v-for="spec in props.specialties" :key="spec.id" class="flex items-center gap-1.5 px-3 py-1.5 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-xs cursor-pointer hover:border-[#005C55]">
                                    <input v-model="form.specialty_ids" type="checkbox" :value="spec.id" class="rounded border-[#BDC9C6] text-[#005C55] focus:ring-[#005C55]" />
                                    <span class="text-[#131B2E] font-medium">{{ spec.name }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Sucursales Checkboxes -->
                        <div class="sm:col-span-2 space-y-1">
                            <label class="block text-xs font-semibold text-[#505F76]">Sedes de Atención</label>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <label v-for="branch in props.branches" :key="branch.id" class="flex items-center gap-1.5 px-3 py-1.5 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-xs cursor-pointer hover:border-[#005C55]">
                                    <input v-model="form.branch_ids" type="checkbox" :value="branch.id" class="rounded border-[#BDC9C6] text-[#005C55] focus:ring-[#005C55]" />
                                    <span class="text-[#131B2E] font-medium">{{ branch.name }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="sm:col-span-2 flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isCreating = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Registrar Odontólogo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
