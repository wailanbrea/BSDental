<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { appUrl } from '@/lib/url'
import { 
    Activity, 
    Search, 
    ArrowLeft 
} from 'lucide-vue-next';
import { ref } from 'vue';

interface TenantDomain {
    id: number;
    domain: string;
    is_primary: boolean;
    is_verified: boolean;
}

interface PlanItem {
    id: string;
    name: string;
}

interface TenantItem {
    id: string;
    name: string;
    slug: string;
    status: string;
    database_name: string;
    plan?: PlanItem | null;
    domains?: TenantDomain[];
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
}

interface Props {
    tenants: PaginatedData<TenantItem>;
    plans: PlanItem[];
    filters: {
        search: string;
        status: string;
    };
}

const props = defineProps<Props>();

const searchInput = ref(props.filters.search || '');
const statusInput = ref(props.filters.status || '');

const handleFilter = () => {
    router.get(appUrl('/platform/tenants'), {
        search: searchInput.value,
        status: statusInput.value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Platform — Directorio de Clínicas" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link :href="appUrl('/platform/dashboard')" class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div class="w-8 h-8 rounded-xl bg-teal-600 flex items-center justify-center text-white">
                        <Activity class="w-5 h-5" />
                    </div>
                    <span class="font-bold text-base text-white">Directorio de Tenants</span>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
            <!-- Filter Bar -->
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center">
                <div class="relative w-full sm:w-80">
                    <Search class="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Buscar por nombre, slug o UUID..."
                        class="w-full pl-9 pr-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                        @keyup.enter="handleFilter"
                    />
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <select
                        v-model="statusInput"
                        class="bg-slate-950 border border-slate-700 text-xs text-slate-300 rounded-lg px-3 py-2 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                        @change="handleFilter"
                    >
                        <option value="">Todos los Estados</option>
                        <option value="active">Activos</option>
                        <option value="suspended">Suspendidos</option>
                        <option value="provisioning">En Provisión</option>
                    </select>
                </div>
            </div>

            <!-- Tenants Table -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3">Organización</th>
                                <th class="px-6 py-3">Dominio</th>
                                <th class="px-6 py-3">Plan Comercial</th>
                                <th class="px-6 py-3">Estado</th>
                                <th class="px-6 py-3 text-right">Detalle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <tr v-for="t in tenants.data" :key="t.id" class="hover:bg-slate-800/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-bold text-white text-sm">{{ t.name }}</div>
                                    <div class="text-slate-500 font-mono text-xs">{{ t.id }}</div>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-300">
                                    <span v-if="t.domains && t.domains.length > 0">{{ t.domains[0].domain }}</span>
                                    <span v-else>{{ t.slug }}.bsdental.app</span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                        {{ t.plan?.name ?? 'Sin Plan Asignado' }}
                                    </span>
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
                                    <Link
                                        :href="`/platform/tenants/${t.id}`"
                                        class="px-3 py-1.5 bg-teal-600 hover:bg-teal-500 text-white rounded-lg font-medium transition"
                                    >
                                        Inspeccionar
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="tenants.data.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                                    No se encontraron organizaciones con los filtros aplicados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</template>