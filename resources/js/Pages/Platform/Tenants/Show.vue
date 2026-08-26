<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { appUrl } from '@/lib/url'
import { 
    Activity, 
    ArrowLeft, 
    Database, 
    Layers, 
    AlertCircle 
} from 'lucide-vue-next';

interface TenantDomain {
    id: number;
    domain: string;
    is_primary: boolean;
    is_verified: boolean;
}

interface PlanItem {
    id: string;
    name: string;
    modules: string[];
}

interface Props {
    tenant: {
        id: string;
        name: string;
        slug: string;
        status: string;
        database_name: string;
        plan?: PlanItem | null;
        domains?: TenantDomain[];
        settings?: Record<string, unknown> | null;
        created_at: string;
    };
    health: {
        database_status: string;
        applied_migrations: number;
        last_checked_at: string;
        error?: string | null;
    };
}

defineProps<Props>();
</script>

<template>
    <Head :title="`Platform — ${tenant.name}`" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link :href="appUrl('/platform/tenants')" class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div class="w-8 h-8 rounded-xl bg-teal-600 flex items-center justify-center text-white">
                        <Activity class="w-5 h-5" />
                    </div>
                    <span class="font-bold text-base text-white">{{ tenant.name }}</span>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">
            <!-- Header Summary Card -->
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <span class="text-xs font-mono text-teal-400 font-semibold">{{ tenant.id }}</span>
                    <h1 class="text-2xl font-extrabold text-white mt-1">{{ tenant.name }}</h1>
                    <p class="text-xs text-slate-400 mt-1">Slug de clínica: <span class="font-mono text-slate-300">{{ tenant.slug }}</span></p>
                </div>
                <div class="flex items-center gap-3">
                    <span 
                        :class="{
                            'bg-emerald-950 text-emerald-400 border-emerald-800': tenant.status === 'active',
                            'bg-amber-950 text-amber-400 border-amber-800': tenant.status === 'suspended',
                            'bg-blue-950 text-blue-400 border-blue-800': tenant.status === 'provisioning',
                        }"
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border capitalize"
                    >
                        {{ tenant.status }}
                    </span>
                </div>
            </div>

            <!-- Grid Details & Health -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Database & Health Status -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
                    <div class="flex items-center gap-2 mb-4">
                        <Database class="w-5 h-5 text-teal-500" />
                        <h2 class="text-sm font-bold text-white">Salud de Base de Datos</h2>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div class="flex justify-between py-2 border-b border-slate-800">
                            <span class="text-slate-400">Ruta / Nombre de BD:</span>
                            <span class="font-mono text-slate-200">{{ tenant.database_name }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800">
                            <span class="text-slate-400">Estado de Conexión:</span>
                            <span 
                                :class="health.database_status === 'healthy' ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'"
                            >
                                {{ health.database_status.toUpperCase() }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-800">
                            <span class="text-slate-400">Migraciones Aplicadas:</span>
                            <span class="font-mono font-bold text-white">{{ health.applied_migrations }}</span>
                        </div>
                        <div v-if="health.error" class="p-3 rounded-lg bg-rose-950/50 border border-rose-800 text-rose-300">
                            <AlertCircle class="w-4 h-4 inline mr-1" />
                            {{ health.error }}
                        </div>
                    </div>
                </div>

                <!-- Commercial Plan & Modules -->
                <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
                    <div class="flex items-center gap-2 mb-4">
                        <Layers class="w-5 h-5 text-teal-500" />
                        <h2 class="text-sm font-bold text-white">Plan Comercial & Módulos</h2>
                    </div>

                    <div class="space-y-4 text-xs">
                        <div class="flex justify-between py-2 border-b border-slate-800">
                            <span class="text-slate-400">Plan Actual:</span>
                            <span class="font-bold text-teal-400">{{ tenant.plan?.name ?? 'Personalizado / Sin Plan' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-2">Módulos Habilitados:</span>
                            <div class="flex flex-wrap gap-2">
                                <span 
                                    v-for="mod in (tenant.plan?.modules ?? [])" 
                                    :key="mod"
                                    class="px-2 py-1 rounded-md bg-slate-800 border border-slate-700 text-slate-300 text-xs font-mono"
                                >
                                    {{ mod }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>