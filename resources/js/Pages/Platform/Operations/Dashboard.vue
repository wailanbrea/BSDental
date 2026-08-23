<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Server, Database, HardDrive, CheckCircle2, AlertTriangle } from 'lucide-vue-next'

interface GlobalMetrics {
    total_tenants: number
    active_tenants: number
    suspended_tenants: number
    total_plans: number
    total_platform_audits: number
}

interface TenantItem {
    id: string
    name: string
    slug: string
    status: string
    plan_name: string
    primary_domain?: string
    db_connected: boolean
    tables_count: number
    backup_status: string
    created_at: string
}

interface PlanItem {
    id: string
    name: string
    price_monthly: number
}

defineProps<{
    metrics: GlobalMetrics
    tenants: TenantItem[]
    plans: PlanItem[]
}>()

function triggerBackup(tenantId: string) {
    useForm({}).post(`/platform/tenants/${tenantId}/backup`)
}
</script>

<template>
    <Head title="Platform Operations & Health — BSDental Landlord" />

    <div class="min-h-screen bg-slate-950 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <Server class="w-6 h-6 text-teal-400" /> Platform Admin Operations & Health
                    </h1>
                    <p class="text-sm text-slate-400">Panel central de observabilidad, salud de bases de datos, backups y planes de suscripción (Landlord)</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/platform/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Platform Home</a>
                </div>
            </div>

            <!-- Global Platform Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Clínicas</span>
                    <div class="text-2xl font-mono font-black text-white">{{ metrics.total_tenants }}</div>
                </div>
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Activas</span>
                    <div class="text-2xl font-mono font-black text-emerald-400">{{ metrics.active_tenants }}</div>
                </div>
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Suspendidas</span>
                    <div class="text-2xl font-mono font-black text-rose-400">{{ metrics.suspended_tenants }}</div>
                </div>
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Planes Comerciales</span>
                    <div class="text-2xl font-mono font-black text-sky-400">{{ metrics.total_plans }}</div>
                </div>
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-1 shadow-lg">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Auditorías Landlord</span>
                    <div class="text-2xl font-mono font-black text-indigo-400">{{ metrics.total_platform_audits }}</div>
                </div>
            </div>

            <!-- Tenants Database Health & Operations Table -->
            <div class="p-6 bg-slate-900 border border-slate-800 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                    <Database class="w-4 h-4" /> Estado de Salud de Bases de Datos por Tenant
                </h2>

                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-[10px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Clínica (Tenant)</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Dominio Principal</th>
                            <th class="px-4 py-3 text-center">Conexión DB</th>
                            <th class="px-4 py-3 text-center">Tablas</th>
                            <th class="px-4 py-3 text-center">Backups</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="t in tenants" :key="t.id" class="hover:bg-slate-800/30 transition">
                            <td class="px-4 py-3">
                                <strong class="text-white block">{{ t.name }}</strong>
                                <span class="font-mono text-[10px] text-slate-500">{{ t.slug }}</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-sky-400">{{ t.plan_name }}</td>
                            <td class="px-4 py-3 font-mono text-teal-400">{{ t.primary_domain || 'Sin dominio' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span v-if="t.db_connected" class="inline-flex items-center gap-1 text-emerald-400 font-bold text-[11px]">
                                    <CheckCircle2 class="w-3.5 h-3.5" /> OK
                                </span>
                                <span v-else class="inline-flex items-center gap-1 text-rose-400 font-bold text-[11px]">
                                    <AlertTriangle class="w-3.5 h-3.5" /> Error
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center font-mono font-bold text-white">{{ t.tables_count }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300">
                                    {{ t.backup_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-teal-300 font-bold rounded-lg text-[11px] transition border border-slate-700"
                                    @click="triggerBackup(t.id)"
                                >
                                    <HardDrive class="w-3 h-3" /> Respaldar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>