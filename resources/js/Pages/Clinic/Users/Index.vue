<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { computed, ref } from 'vue'
import { KeyRound, Pencil, Plus, ShieldCheck, Users } from 'lucide-vue-next'
import type { PageProps } from '@/types'

interface Permission { id: string; name: string }
interface Role { id: string; name: string; permissions: Permission[] }
interface Branch { id: string; name: string }
interface ClinicUser {
    id: string; name: string; email: string; phone: string | null; status: string
    last_login_at: string | null; roles: Role[]; branches: Branch[]
}

const props = defineProps<{ users: ClinicUser[]; roles: Role[]; permissions: Permission[]; branches: Branch[] }>()
const page = usePage<PageProps>()
const modalOpen = ref(false)
const roleModalOpen = ref(false)
const editingId = ref<string | null>(null)
const editingRole = ref<Role | null>(null)
const canManage = computed(() => page.props.auth?.user?.permissions?.includes('users.manage') || page.props.auth?.user?.roles?.includes('Owner'))

const form = useForm({
    name: '', email: '', phone: '', status: 'active', password: '', role: '', branch_ids: [] as string[],
})
const roleForm = useForm({ permissions: [] as string[] })
const permissionGroups = computed(() => Object.entries(props.permissions.reduce((groups, permission) => {
    const module = permission.name.split('.')[0]
    ;(groups[module] ||= []).push(permission)
    return groups
}, {} as Record<string, Permission[]>)))

function openCreate() {
    editingId.value = null
    form.reset()
    form.clearErrors()
    form.status = 'active'
    form.role = props.roles[0]?.name || ''
    modalOpen.value = true
}

function openEdit(user: ClinicUser) {
    editingId.value = user.id
    form.clearErrors()
    form.name = user.name
    form.email = user.email
    form.phone = user.phone || ''
    form.status = user.status
    form.password = ''
    form.role = user.roles[0]?.name || ''
    form.branch_ids = user.branches.map((branch) => branch.id)
    modalOpen.value = true
}

function submit() {
    if (editingId.value) {
        form.put(appUrl(`/users/${editingId.value}`), { preserveScroll: true, onSuccess: () => modalOpen.value = false })
        return
    }
    form.post(appUrl('/users'), { preserveScroll: true, onSuccess: () => modalOpen.value = false })
}

function openRole(role: Role) {
    editingRole.value = role
    roleForm.permissions = role.permissions.map((permission) => permission.name)
    roleForm.clearErrors()
    roleModalOpen.value = true
}

function submitRole() {
    if (!editingRole.value) return
    roleForm.put(appUrl(`/roles/${editingRole.value.id}/permissions`), { preserveScroll: true, onSuccess: () => roleModalOpen.value = false })
}

function roleLabel(role: string) {
    return ({ Owner: 'Propietario', ClinicDirector: 'Director clínico', GeneralDentist: 'Odontólogo general', SpecialistDentist: 'Especialista', Hygienist: 'Higienista', Receptionist: 'Recepción', Cashier: 'Caja', LabTechnician: 'Laboratorio', InventoryManager: 'Inventario' } as Record<string, string>)[role] || role
}
</script>

<template>
    <ClinicLayout>
        <Head title="Usuarios y permisos" />
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-end justify-between gap-4 border-b border-[#D8E0DE] pb-5">
                <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-[#006B63]">Seguridad de la clínica</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-[#131B2E]">Usuarios, roles y sucursales</h1><p class="mt-2 text-sm text-[#667085]">Controla quién puede acceder a cada módulo y desde qué sedes.</p></div>
                <button v-if="canManage" type="button" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-semibold text-white hover:bg-[#004A45]" @click="openCreate"><Plus class="h-4 w-4" /> Nuevo usuario</button>
            </header>

            <section class="grid gap-4 sm:grid-cols-3">
                <article class="border border-[#BDC9C6] bg-white p-5"><div class="flex items-center gap-3"><Users class="h-5 w-5 text-[#006B63]" /><span class="text-sm font-semibold text-[#455653]">Usuarios</span></div><p class="mt-3 font-mono text-3xl font-bold text-[#131B2E]">{{ users.length }}</p></article>
                <article class="border border-[#BDC9C6] bg-white p-5"><div class="flex items-center gap-3"><ShieldCheck class="h-5 w-5 text-[#006B63]" /><span class="text-sm font-semibold text-[#455653]">Roles disponibles</span></div><p class="mt-3 font-mono text-3xl font-bold text-[#131B2E]">{{ roles.length }}</p></article>
                <article class="border border-[#BDC9C6] bg-white p-5"><div class="flex items-center gap-3"><KeyRound class="h-5 w-5 text-[#006B63]" /><span class="text-sm font-semibold text-[#455653]">Cuentas activas</span></div><p class="mt-3 font-mono text-3xl font-bold text-[#131B2E]">{{ users.filter(user => user.status === 'active').length }}</p></article>
            </section>

            <section class="space-y-3"><div><h2 class="text-xl font-bold text-[#131B2E]">Matriz de roles</h2><p class="text-sm text-[#667085]">Cada usuario hereda los permisos de su rol. El rol Propietario es inmutable.</p></div><div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"><article v-for="role in roles" :key="role.id" class="border border-[#BDC9C6] bg-white p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-[#131B2E]">{{ roleLabel(role.name) }}</h3><p class="mt-1 font-mono text-xs text-[#667085]">{{ role.permissions.length }} permisos</p></div><button v-if="canManage && role.name !== 'Owner'" type="button" class="text-xs font-bold text-[#006B63] hover:underline" @click="openRole(role)">Configurar</button></div><div class="mt-3 flex flex-wrap gap-1"><span v-for="permission in role.permissions.slice(0, 4)" :key="permission.id" class="bg-[#F1F5F4] px-2 py-1 font-mono text-[10px] text-[#455653]">{{ permission.name }}</span><span v-if="role.permissions.length > 4" class="px-2 py-1 text-[10px] text-[#667085]">+{{ role.permissions.length - 4 }}</span></div></article></div></section>

            <section class="overflow-hidden border border-[#BDC9C6] bg-white">
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-[#F1F5F4] text-xs uppercase tracking-wider text-[#52615E]"><tr><th class="px-4 py-3">Usuario</th><th class="px-4 py-3">Rol</th><th class="px-4 py-3">Sucursales</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Último acceso</th><th class="px-4 py-3 text-right">Acción</th></tr></thead><tbody class="divide-y divide-[#E1E7E5]"><tr v-for="user in users" :key="user.id" class="hover:bg-[#F7FAF9]"><td class="px-4 py-3"><p class="font-semibold text-[#131B2E]">{{ user.name }}</p><p class="text-xs text-[#667085]">{{ user.email }}</p></td><td class="px-4 py-3"><span class="border border-[#B7D9D4] bg-[#F1FAF8] px-2 py-1 text-xs font-bold text-[#006B63]">{{ roleLabel(user.roles[0]?.name || 'Sin rol') }}</span></td><td class="px-4 py-3 text-[#455653]">{{ user.branches.length ? user.branches.map(branch => branch.name).join(', ') : 'Todas las sucursales' }}</td><td class="px-4 py-3"><span :class="user.status === 'active' ? 'text-[#006B63]' : 'text-[#B42318]'" class="text-xs font-bold uppercase">{{ user.status }}</span></td><td class="px-4 py-3 font-mono text-xs text-[#667085]">{{ user.last_login_at ? new Date(user.last_login_at).toLocaleString('es-DO') : 'Nunca' }}</td><td class="px-4 py-3 text-right"><button v-if="canManage" type="button" class="inline-flex items-center gap-1 text-xs font-bold text-[#006B63] hover:underline" @click="openEdit(user)"><Pencil class="h-3.5 w-3.5" /> Editar</button></td></tr></tbody></table></div>
            </section>

            <div v-if="modalOpen" class="fixed inset-0 z-[70] grid place-items-center bg-[#131B2E]/45 p-4" @click.self="modalOpen = false">
                <section class="max-h-[92vh] w-full max-w-2xl overflow-y-auto border border-[#BDC9C6] bg-white shadow-2xl">
                    <header class="flex items-center justify-between border-b border-[#D8E0DE] px-6 py-4"><div><h2 class="text-xl font-bold text-[#131B2E]">{{ editingId ? 'Editar usuario' : 'Crear usuario' }}</h2><p class="text-xs text-[#667085]">Los cambios quedan registrados en auditoría.</p></div><button type="button" class="text-2xl text-[#667085]" @click="modalOpen = false">×</button></header>
                    <form class="space-y-5 p-6" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold text-[#455653]">Nombre<input v-model="form.name" required class="mt-1 h-10 w-full border border-[#BDC9C6] px-3 font-normal text-[#131B2E]" /></label><label class="text-sm font-semibold text-[#455653]">Correo<input v-model="form.email" type="email" required class="mt-1 h-10 w-full border border-[#BDC9C6] px-3 font-normal text-[#131B2E]" /></label><label class="text-sm font-semibold text-[#455653]">Teléfono<input v-model="form.phone" class="mt-1 h-10 w-full border border-[#BDC9C6] px-3 font-normal text-[#131B2E]" /></label><label class="text-sm font-semibold text-[#455653]">Contraseña {{ editingId ? '(opcional)' : '' }}<input v-model="form.password" type="password" :required="!editingId" minlength="12" class="mt-1 h-10 w-full border border-[#BDC9C6] px-3 font-normal text-[#131B2E]" /></label><label class="text-sm font-semibold text-[#455653]">Rol<select v-model="form.role" required class="mt-1 h-10 w-full border border-[#BDC9C6] bg-white px-3 font-normal text-[#131B2E]"><option v-for="role in roles" :key="role.id" :value="role.name">{{ roleLabel(role.name) }}</option></select></label><label class="text-sm font-semibold text-[#455653]">Estado<select v-model="form.status" required class="mt-1 h-10 w-full border border-[#BDC9C6] bg-white px-3 font-normal text-[#131B2E]"><option value="active">Activo</option><option value="inactive">Inactivo</option><option value="locked">Bloqueado</option></select></label></div>
                        <fieldset><legend class="text-sm font-semibold text-[#455653]">Sucursales permitidas</legend><p class="mb-3 text-xs text-[#667085]">Sin selección significa acceso a todas las sucursales.</p><div class="grid gap-2 sm:grid-cols-2"><label v-for="branch in branches" :key="branch.id" class="flex items-center gap-2 border border-[#D8E0DE] p-3 text-sm text-[#263633]"><input v-model="form.branch_ids" type="checkbox" :value="branch.id" class="accent-[#005C55]" /> {{ branch.name }}</label></div></fieldset>
                        <div v-if="Object.keys(form.errors).length" class="border border-[#F5A3A0] bg-[#FFF1F0] p-3 text-xs text-[#B42318]"><p v-for="(error, key) in form.errors" :key="key">{{ error }}</p></div>
                        <footer class="flex justify-end gap-3 border-t border-[#D8E0DE] pt-4"><button type="button" class="h-10 border border-[#BDC9C6] px-4 text-sm font-semibold text-[#455653]" @click="modalOpen = false">Cancelar</button><button type="submit" :disabled="form.processing" class="h-10 bg-[#005C55] px-5 text-sm font-semibold text-white disabled:opacity-50">{{ editingId ? 'Guardar cambios' : 'Crear usuario' }}</button></footer>
                    </form>
                </section>
            </div>

            <div v-if="roleModalOpen && editingRole" class="fixed inset-0 z-[70] grid place-items-center bg-[#131B2E]/45 p-4" @click.self="roleModalOpen = false">
                <section class="max-h-[92vh] w-full max-w-3xl overflow-y-auto border border-[#BDC9C6] bg-white shadow-2xl"><header class="flex items-center justify-between border-b border-[#D8E0DE] px-6 py-4"><div><h2 class="text-xl font-bold text-[#131B2E]">Permisos · {{ roleLabel(editingRole.name) }}</h2><p class="text-xs text-[#667085]">El cambio afecta a todos los usuarios con este rol.</p></div><button type="button" class="text-2xl text-[#667085]" @click="roleModalOpen = false">×</button></header><form class="space-y-5 p-6" @submit.prevent="submitRole"><div class="grid gap-4 sm:grid-cols-2"><fieldset v-for="[module, modulePermissions] in permissionGroups" :key="module" class="border border-[#D8E0DE] p-4"><legend class="px-1 text-xs font-bold uppercase tracking-wider text-[#006B63]">{{ module }}</legend><label v-for="permission in modulePermissions" :key="permission.id" class="mt-2 flex items-center gap-2 font-mono text-xs text-[#455653]"><input v-model="roleForm.permissions" type="checkbox" :value="permission.name" class="accent-[#005C55]" /> {{ permission.name }}</label></fieldset></div><p v-if="roleForm.errors.permissions" class="border border-[#F5A3A0] bg-[#FFF1F0] p-3 text-xs text-[#B42318]">{{ roleForm.errors.permissions }}</p><footer class="flex justify-end gap-3 border-t border-[#D8E0DE] pt-4"><button type="button" class="h-10 border border-[#BDC9C6] px-4 text-sm font-semibold text-[#455653]" @click="roleModalOpen = false">Cancelar</button><button type="submit" :disabled="roleForm.processing" class="h-10 bg-[#005C55] px-5 text-sm font-semibold text-white disabled:opacity-50">Guardar permisos</button></footer></form></section>
            </div>
        </div>
    </ClinicLayout>
</template>
