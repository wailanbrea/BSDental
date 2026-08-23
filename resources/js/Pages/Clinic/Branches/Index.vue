<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Building2, Plus, Trash2, MapPin, Phone, Mail, X } from 'lucide-vue-next'

interface Room {
    id: string
    name: string
    code: string | null
    is_active: boolean
}

interface Branch {
    id: string
    name: string
    code: string | null
    address: string | null
    phone: string | null
    email: string | null
    is_main: boolean
    is_active: boolean
    rooms_count: number
    professionals_count: number
    rooms: Room[]
}

const props = defineProps<{
    branches: Branch[]
}>()

const isCreatingBranch = ref(false)
const selectedBranchForRoom = ref<Branch | null>(null)

const branchForm = useForm({
    name: '',
    code: '',
    address: '',
    phone: '',
    email: '',
    is_main: false,
})

const roomForm = useForm({
    name: '',
    code: '',
})

function submitCreateBranch() {
    branchForm.post('/branches', {
        onSuccess: () => {
            branchForm.reset()
            isCreatingBranch.value = false
        },
    })
}

function submitCreateRoom() {
    if (!selectedBranchForRoom.value) return
    roomForm.post(`/branches/${selectedBranchForRoom.value.id}/rooms`, {
        onSuccess: () => {
            roomForm.reset()
            selectedBranchForRoom.value = null
        },
    })
}

function deleteBranch(branch: Branch) {
    if (confirm(`¿Estás seguro de eliminar la sucursal "${branch.name}"?`)) {
        useForm({}).delete(`/branches/${branch.id}`)
    }
}

function deleteRoom(room: Room) {
    if (confirm(`¿Eliminar consultorio "${room.name}"?`)) {
        useForm({}).delete(`/rooms/${room.id}`)
    }
}
</script>

<template>
    <ClinicLayout>
<div class="clinical-precision-page">
    <Head title="Sucursales y Sillones Dentales — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <Building2 class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Sucursales y Sillones Dentales</h1>
                        <p class="text-sm text-slate-400">Gestión de sedes físicas, consultorios y distribución de sillones clínicos</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Volver al Dashboard</a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-semibold rounded-lg shadow-lg shadow-teal-500/20 transition"
                        @click="isCreatingBranch = true"
                    >
                        <Plus class="w-4 h-4" /> Nueva Sucursal
                    </button>
                </div>
            </div>

            <!-- Branch Creation Modal -->
            <div v-if="isCreatingBranch" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Registrar Nueva Sucursal</h2>
                    <button class="text-slate-400 hover:text-white" @click="isCreatingBranch = false"><X class="w-5 h-5" /></button>
                </div>
                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitCreateBranch">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nombre de la Sucursal</label>
                        <input v-model="branchForm.name" type="text" required placeholder="Ej. Sede Principal" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Código Identificador</label>
                        <input v-model="branchForm.code" type="text" placeholder="Ej. SUC-01" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Dirección</label>
                        <input v-model="branchForm.address" type="text" placeholder="Av. Principal, Edif. Médico" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Teléfono</label>
                        <input v-model="branchForm.phone" type="text" placeholder="+58 212 555-0101" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div class="col-span-2 flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 text-sm text-slate-300">
                            <input v-model="branchForm.is_main" type="checkbox" class="rounded bg-slate-900 border-slate-700 text-teal-500" />
                            Definir como Sede Principal
                        </label>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-600" @click="isCreatingBranch = false">Cancelar</button>
                            <button type="submit" :disabled="branchForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-sm font-semibold rounded-lg hover:bg-teal-400">Guardar Sucursal</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Room Creation Modal -->
            <div v-if="selectedBranchForRoom" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Añadir Consultorio a "{{ selectedBranchForRoom.name }}"</h2>
                    <button class="text-slate-400 hover:text-white" @click="selectedBranchForRoom = null"><X class="w-5 h-5" /></button>
                </div>
                <form class="grid grid-cols-1 md:grid-cols-2 gap-4" @submit.prevent="submitCreateRoom">
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Nombre del Consultorio / Sillón</label>
                        <input v-model="roomForm.name" type="text" required placeholder="Ej. Sillón 1 - Cirugía" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Código</label>
                        <input v-model="roomForm.code" type="text" placeholder="Ej. SIL-01" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm" />
                    </div>
                    <div class="col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-sm font-medium rounded-lg hover:bg-slate-600" @click="selectedBranchForRoom = null">Cancelar</button>
                        <button type="submit" :disabled="roomForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-sm font-semibold rounded-lg hover:bg-teal-400">Crear Sillón</button>
                    </div>
                </form>
            </div>

            <!-- Branches Listing -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div v-for="branch in props.branches" :key="branch.id" class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-2xl space-y-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-lg font-bold text-white">{{ branch.name }}</h3>
                                <span v-if="branch.is_main" class="px-2 py-0.5 text-xs font-semibold bg-teal-500/10 text-teal-400 border border-teal-500/30 rounded-full">Principal</span>
                            </div>
                            <p v-if="branch.code" class="text-xs text-slate-400 font-mono">{{ branch.code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="text-xs font-semibold text-teal-400 hover:text-teal-300 bg-teal-500/10 px-2.5 py-1.5 rounded-lg border border-teal-500/20" @click="selectedBranchForRoom = branch">
                                + Sillón
                            </button>
                            <button class="p-1.5 text-slate-400 hover:text-rose-400 transition" @click="deleteBranch(branch)">
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="text-xs text-slate-400 space-y-1">
                        <p v-if="branch.address" class="flex items-center gap-1.5"><MapPin class="w-3.5 h-3.5 text-slate-500" /> {{ branch.address }}</p>
                        <p v-if="branch.phone" class="flex items-center gap-1.5"><Phone class="w-3.5 h-3.5 text-slate-500" /> {{ branch.phone }}</p>
                        <p v-if="branch.email" class="flex items-center gap-1.5"><Mail class="w-3.5 h-3.5 text-slate-500" /> {{ branch.email }}</p>
                    </div>

                    <!-- Rooms in Branch -->
                    <div class="border-t border-slate-700/60 pt-3 space-y-2">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Consultorios / Sillones ({{ branch.rooms?.length || 0 }})</span>
                        <div class="grid grid-cols-2 gap-2">
                            <div v-for="room in branch.rooms" :key="room.id" class="flex items-center justify-between p-2 bg-slate-900/60 border border-slate-700/40 rounded-lg">
                                <span class="text-xs text-slate-200">{{ room.name }}</span>
                                <button class="text-slate-500 hover:text-rose-400" @click="deleteRoom(room)"><X class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
