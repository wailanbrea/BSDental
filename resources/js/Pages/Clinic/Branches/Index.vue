<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Building2, Plus, Trash2, MapPin, Phone, Mail, X, Armchair } from 'lucide-vue-next'

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
        <Head title="Sucursales y Sillones Dentales — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <Building2 class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Sucursales y Sillones Dentales
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Gestión de sedes físicas, consultorios y distribución de sillones clínicos
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isCreatingBranch = true"
                    >
                        <Plus class="w-3.5 h-3.5" /> Nueva Sucursal
                    </button>
                </div>
            </div>

            <!-- Branches Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div 
                    v-for="branch in props.branches" 
                    :key="branch.id" 
                    class="bg-white border border-[#E2E8F0] rounded-xl shadow-xs p-5 space-y-4 hover:border-[#BDC9C6] transition"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-[#131B2E]">{{ branch.name }}</h3>
                                <span v-if="branch.is_main" class="px-2 py-0.5 text-[10px] font-bold bg-[#005C55]/10 text-[#005C55] border border-[#005C55]/20 rounded-full">
                                    Sede Principal
                                </span>
                            </div>
                            <p v-if="branch.code" class="text-xs text-[#505F76] font-mono mt-0.5">Código: {{ branch.code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button 
                                class="text-xs font-semibold text-[#005C55] hover:text-[#004742] bg-[#005C55]/10 hover:bg-[#005C55]/20 px-2.5 py-1.5 rounded-lg transition" 
                                @click="selectedBranchForRoom = branch"
                            >
                                + Sillón
                            </button>
                            <button 
                                class="p-1.5 text-[#505F76] hover:text-[#BA1A1A] transition" 
                                title="Eliminar sucursal"
                                @click="deleteBranch(branch)"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="text-xs text-[#505F76] space-y-1.5 bg-[#F8FAFC] p-3 rounded-lg border border-[#E2E8F0]">
                        <p v-if="branch.address" class="flex items-center gap-2 text-[#131B2E]">
                            <MapPin class="w-3.5 h-3.5 text-[#505F76]" /> {{ branch.address }}
                        </p>
                        <p v-if="branch.phone" class="flex items-center gap-2">
                            <Phone class="w-3.5 h-3.5 text-[#505F76]" /> {{ branch.phone }}
                        </p>
                        <p v-if="branch.email" class="flex items-center gap-2">
                            <Mail class="w-3.5 h-3.5 text-[#505F76]" /> {{ branch.email }}
                        </p>
                    </div>

                    <!-- Rooms in Branch -->
                    <div class="border-t border-[#E2E8F0] pt-3 space-y-2">
                        <span class="text-xs font-semibold text-[#505F76] uppercase tracking-wider block">
                            Consultorios / Sillones ({{ branch.rooms?.length || 0 }})
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div 
                                v-for="room in branch.rooms" 
                                :key="room.id" 
                                class="flex items-center justify-between p-2.5 bg-white border border-[#E2E8F0] rounded-lg shadow-2xs text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <Armchair class="w-3.5 h-3.5 text-[#005C55]" />
                                    <span class="font-medium text-[#131B2E]">{{ room.name }}</span>
                                </div>
                                <button class="text-[#505F76] hover:text-[#BA1A1A] transition" title="Eliminar sillón" @click="deleteRoom(room)">
                                    <X class="w-3.5 h-3.5" />
                                </button>
                            </div>
                            <div v-if="!branch.rooms?.length" class="col-span-2 text-center text-xs text-[#505F76] italic py-2">
                                No hay sillones configurados en esta sucursal.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Branch Creation Modal -->
            <div v-if="isCreatingBranch" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isCreatingBranch = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Building2 class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Registrar Nueva Sucursal</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isCreatingBranch = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-3.5" @submit.prevent="submitCreateBranch">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombre de la Sucursal *</label>
                            <input v-model="branchForm.name" type="text" required placeholder="Ej. Sede Central Dental" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Código Identificador</label>
                            <input v-model="branchForm.code" type="text" placeholder="Ej. SUC-01" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Teléfono</label>
                            <input v-model="branchForm.phone" type="text" placeholder="+58 212 555-0101" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Dirección Física</label>
                            <input v-model="branchForm.address" type="text" placeholder="Av. Principal, Edificio Consultorios Médicos" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between pt-3 border-t border-[#E2E8F0]">
                            <label class="flex items-center gap-2 text-xs font-medium text-[#131B2E]">
                                <input v-model="branchForm.is_main" type="checkbox" class="rounded border-[#BDC9C6] text-[#005C55] focus:ring-[#005C55]" />
                                Definir como Sede Principal
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isCreatingBranch = false">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="branchForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                    Guardar Sucursal
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Room Creation Modal -->
            <div v-if="selectedBranchForRoom" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="selectedBranchForRoom = null">
                <div class="w-full max-w-md bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Armchair class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Añadir Sillón a "{{ selectedBranchForRoom.name }}"</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="selectedBranchForRoom = null">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitCreateRoom">
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombre del Consultorio / Sillón *</label>
                            <input v-model="roomForm.name" type="text" required placeholder="Ej. Sillón 1 - Cirugía" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Código del Sillón</label>
                            <input v-model="roomForm.code" type="text" placeholder="Ej. SIL-01" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="selectedBranchForRoom = null">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="roomForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Crear Sillón
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
