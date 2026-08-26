<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { appUrl } from '@/lib/url'
import { 
    Activity, 
    LogOut, 
    Building2, 
    CheckCircle2, 
    AlertTriangle, 
    RefreshCw, 
    Plus
} from 'lucide-vue-next';

interface TenantItem {
    id: string;
    name: string;
    slug: string;
    status: string;
    database_name: string;
    created_at: string;
    domains?: Array<{ domain: string; is_primary: boolean; is_verified: boolean }>;
}

interface Props {
    user: {
        name: string;
        email: string;
        role: string;
    };
    metrics: {
        total_tenants: number;
        active_tenants: number;
        suspended_tenants: number;
        provisioning_tenants: number;
    };
    tenants: TenantItem[];
}

defineProps<Props>();

const logout = () => {
    router.post(appUrl('/platform/logout'));
};
</script>

<template>
    <Head title="Platform Admin — Centro de Control" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-teal-500 selection:text-white">
        <!-- Top Nav -->
        <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 rounded-xl bg-teal-600 flex items-center justify-center text-white shadow-xs">
                        <Activity class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-base text-white tracking-tight">BSDental Platform</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-teal-950 text-teal-400 border border-teal-800">
                                Landlord Plane
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-4">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-semibold text-slate-200">{{ user.name }}</div>
                        <div class="text-xs text-slate-500">{{ user.email }} ({{ user.role }})</div>
                    </div>
                    <button
                        class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:text-white hover:bg-slate-700 transition"
                        title="Cerrar sesión"
                        @click="logout"
                    >
                        <LogOut class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
            <!-- Header Action Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight">Gestión Global de Tenants</h1>
                    <p class="text-xs text-slate-400 mt-1">Supervisión, estado de bases de datos y aprovisionamiento multi-tenant.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-500 text-white font-medium text-xs shadow-xs transition">
                        <Plus class="w-4 h-4" />
                        Nuevo Tenant
                    </button>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
                    <span class="text-xs text-slate-400 font-medium">Total Organizaciones</span>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ metrics.total_tenants }}</p>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
                    <span class="text-xs text-teal-400 font-medium flex items-center gap-1.5">
                        <CheckCircle2 class="w-3.5 h-3.5 text-teal-500" />
                        Activas
                    </span>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ metrics.active_tenants }}</p>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
                    <span class="text-xs text-amber-400 font-medium flex items-center gap-1.5">
                        <AlertTriangle class="w-3.5 h-3.5 text-amber-500" />
                        Suspendidas
                    </span>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ metrics.suspended_tenants }}</p>
                </div>
                <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl">
                    <span class="text-xs text-blue-400 font-medium flex items-center gap-1.5">
                        <RefreshCw class="w-3.5 h-3.5 text-blue-500" />
                        En Provisión
                    </span>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ metrics.provisioning_tenants }}</p>
                </div>
            </div>

            <!-- Tenants List Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <Building2 class="w-4 h-4 text-teal-500" />
                        Directorio de Clínicas Registradas
                    </h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3">Organización</th>
                                <th class="px-6 py-3">Slug / Dominio</th>
                                <th class="px-6 py-3">Base de Datos</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-800/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-white text-sm">{{ t.name }}</div>
                                    <div class="text-slate-500 font-mono text-xs">{{ t.id }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-300">
                                    <span v-if="t.domains && t.domains.length > 0">
                                        {{ t.domains[0].domain }}
                                    </span>
                                    <span v-else>{{ t.slug }}.bsdental.app</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-400">
                                    {{ t.database_name }}
                                </td>
                                <td class="px-6 py-4">
                                    <span 
                                        :class="{
                                            'bg-emerald-950 text-emerald-400 border-emerald-800': t.status === 'active',
                                            'bg-amber-950 text-amber-400 border-amber-800': t.status === 'suspended',
                                            'bg-blue-950 text-blue-400 border-blue-800': t.status === 'provisioning',
                                        }"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border capitalize"
                                    >
                                        {{ t.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button class="px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-md font-medium transition">
                                        Inspeccionar
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="tenants.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    No hay organizaciones registradas en la base central.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>