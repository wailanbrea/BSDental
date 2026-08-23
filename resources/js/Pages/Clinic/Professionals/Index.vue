<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { UserCheck, Plus, Trash2, Phone, Mail, X } from 'lucide-vue-next'

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
    color: '#0d9488',
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
<div class="clinical-precision-page">
    <Head title="Equipo Médico y Especialistas — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <UserCheck class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Equipo Médico y Especialistas</h1>
                        <p class="text-sm text-slate-400">Directorio de odontólogos, colegiaturas, asignación de especialidades y sedes</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Volver al Dashboard</a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-semibold rounded-lg shadow-lg shadow-teal-500/20 transition"
                        @click="isCreating = true"
                    >
                        <Plus class="w-4 h-4" /> Nuevo Profesional
                    </button>
                </div>
            </div>

            <!-- Create Modal -->
            <div v-if="isCreating" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Odontólogo / Especialista</h2>
                    <button class="text-slate-400 hover:text-white" @click="isCreating = false"><X class="w-5 h-5" /></button>
                </div>

                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitCreate">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nombres</label>
                        <input v-model="form.first_name" type="text" required placeholder="Ej. Carlos" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Apellidos</label>
                        <input v-model="form.last_name" type="text" required placeholder="Ej. Mendoza" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Número de Colegiatura / Matrícula</label>
                        <input v-model="form.license_number" type="text" placeholder="Ej. COL-8921" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Color Identificador en Agenda</label>
                        <input v-model="form.color" type="color" class="w-full h-10 p-1 bg-slate-900 border border-slate-700 rounded-lg cursor-pointer" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Teléfono</label>
                        <input v-model="form.phone" type="text" placeholder="+58 412 123-4567" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Correo Electrónico</label>
                        <input v-model="form.email" type="email" placeholder="carlos.mendoza@clinica.com" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>

                    <!-- Especialidades Checkboxes -->
                    <div class="col-span-2 space-y-1">
                        <label class="block text-xs font-medium text-slate-400">Especialidades</label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <label v-for="spec in props.specialties" :key="spec.id" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs cursor-pointer hover:border-teal-500">
                                <input v-model="form.specialty_ids" type="checkbox" :value="spec.id" class="rounded bg-slate-800 border-slate-700 text-teal-500" />
                                <span class="text-slate-200">{{ spec.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Sucursales Checkboxes -->
                    <div class="col-span-2 space-y-1">
                        <label class="block text-xs font-medium text-slate-400">Sedes de Atención</label>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <label v-for="branch in props.branches" :key="branch.id" class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs cursor-pointer hover:border-teal-500">
                                <input v-model="form.branch_ids" type="checkbox" :value="branch.id" class="rounded bg-slate-800 border-slate-700 text-teal-500" />
                                <span class="text-slate-200">{{ branch.name }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="col-span-2 flex justify-end gap-2 pt-3">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-600" @click="isCreating = false">Cancelar</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-sm font-semibold rounded-lg hover:bg-teal-400">Registrar Odontólogo</button>
                    </div>
                </form>
            </div>

            <!-- Professionals Listing -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div v-for="pro in props.professionals" :key="pro.id" class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shadow-inner" :style="{ backgroundColor: pro.color }">
                                {{ pro.first_name[0] }}{{ pro.last_name[0] }}
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-white">Dr(a). {{ pro.full_name }}</h3>
                                <p v-if="pro.license_number" class="text-xs text-slate-400 font-mono">Col. {{ pro.license_number }}</p>
                            </div>
                        </div>
                        <button class="p-1.5 text-slate-400 hover:text-rose-400 transition" @click="deleteProfessional(pro)">
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="text-xs text-slate-400 space-y-1">
                        <p v-if="pro.phone" class="flex items-center gap-1.5"><Phone class="w-3.5 h-3.5 text-slate-500" /> {{ pro.phone }}</p>
                        <p v-if="pro.email" class="flex items-center gap-1.5"><Mail class="w-3.5 h-3.5 text-slate-500" /> {{ pro.email }}</p>
                    </div>

                    <!-- Specialties Tags -->
                    <div class="space-y-1.5 border-t border-slate-700/60 pt-3">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Especialidades</span>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="spec in pro.specialties" :key="spec.id" class="px-2 py-0.5 text-xs bg-teal-500/10 text-teal-400 border border-teal-500/30 rounded-md">
                                {{ spec.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
