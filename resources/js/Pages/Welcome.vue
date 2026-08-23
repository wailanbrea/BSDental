<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { 
    Activity, 
    ShieldCheck, 
    Layers, 
    Server, 
    CheckCircle2, 
    Calendar, 
    FileText, 
    Boxes 
} from 'lucide-vue-next';

interface Props {
    appName?: string;
    version?: string;
    phpVersion?: string;
    laravelVersion?: string;
    environment?: string;
}

withDefaults(defineProps<Props>(), {
    appName: 'BSDental',
    version: '4.0.0-dev',
    phpVersion: '8.5 / 8.2 local',
    laravelVersion: '12.x / 13.x',
    environment: 'development'
});

const modules = [
    { name: 'Multi-Tenancy Aislado', desc: 'Database-per-tenant con Spatie y TenantContext centralizado.', icon: Layers, status: 'Fase 1' },
    { name: 'Seguridad ASVS 5.0 L2/L3', desc: 'Argon2id, 2FA, RBAC estricto, CSP, tenant isolation suite.', icon: ShieldCheck, status: 'Fase 3' },
    { name: 'Expediente & Odontograma', desc: 'Historia clínica inmutable con enmiendas y odontograma interactivo.', icon: FileText, status: 'Fase 8-9' },
    { name: 'Agenda Quirúrgica & Turnos', desc: 'Control de citas, salas, profesionales y eventos en tiempo real.', icon: Calendar, status: 'Fase 7' },
    { name: 'Presupuestos & Producción', desc: 'Invariantes financieras estrictas (producción != cobro != caja).', icon: Activity, status: 'Fase 10-12' },
    { name: 'Cadena de Suministro & Lab', desc: 'Kárdex de movimientos, lotes, consumos idempotentes y órdenes lab.', icon: Boxes, status: 'Fase 11' },
];
</script>

<template>
    <Head :title="'Plataforma Odontológica SaaS'" />

    <div class="min-h-screen bg-[#F8FAFC] flex flex-col justify-between selection:bg-[#CCFBF1] selection:text-[#0F766E]">
        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#0F766E] to-[#115E59] flex items-center justify-center text-white shadow-sm">
                        <Activity class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-lg text-slate-900 tracking-tight">{{ appName }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#CCFBF1] text-[#0F766E]">
                                v4 Multi-Tenant
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium">Dental Practice Management & Clinical Platform</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="hidden sm:flex items-center space-x-2 px-3 py-1 bg-slate-100 rounded-lg text-xs text-slate-600 font-mono">
                        <Server class="w-3.5 h-3.5 text-slate-500" />
                        <span>Env: {{ environment }}</span>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Toolchain Ready
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Hero & Architecture Overview -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 flex-1 flex flex-col justify-center">
            <!-- Hero Card -->
            <div class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-10 shadow-xs mb-8">
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-teal-50 text-[#0F766E] border border-teal-200 text-xs font-semibold mb-4">
                        <ShieldCheck class="w-4 h-4" />
                        Arquitectura Aprobada — Development Pack v4
                    </div>
                    <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight leading-tight">
                        Sistema Clínico & ERP Odontológico de Alta Precisión
                    </h1>
                    <p class="mt-4 text-base text-slate-600 leading-relaxed">
                        BSDental v4 opera sobre una base de código unificada con aislamiento físico <strong class="text-slate-800">Database-per-Tenant</strong>. 
                        Garantiza consistencia clínica, separación estricta de dominios, auditoría inmutable y gobernanza multi-sucursal.
                    </p>
                </div>

                <!-- Environment Badges -->
                <div class="mt-8 pt-6 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200/60">
                        <span class="text-slate-500 block font-medium">Stack Backend</span>
                        <span class="text-slate-800 font-semibold font-mono text-sm">Laravel + PHP {{ phpVersion }}</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200/60">
                        <span class="text-slate-500 block font-medium">Frontend Bridge</span>
                        <span class="text-slate-800 font-semibold font-mono text-sm">Vue 3.5 + Inertia.js</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200/60">
                        <span class="text-slate-500 block font-medium">Tipado & Estilos</span>
                        <span class="text-slate-800 font-semibold font-mono text-sm">TS Strict + Tailwind 4</span>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg border border-slate-200/60">
                        <span class="text-slate-500 block font-medium">Aislamiento Tenancy</span>
                        <span class="text-slate-800 font-semibold font-mono text-sm">DB per Tenant</span>
                    </div>
                </div>
            </div>

            <!-- Modules Roadmap Grid -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-slate-900 tracking-tight">Pilares Arquitectónicos y Módulos</h2>
                    <span class="text-xs text-slate-500">Plan de ejecución secuencial por ID</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="mod in modules" 
                        :key="mod.name"
                        class="bg-white p-5 rounded-xl border border-slate-200 hover:border-teal-300 transition-colors shadow-xs flex flex-col justify-between group"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-[#0F766E] group-hover:bg-[#CCFBF1] transition-colors">
                                    <component :is="mod.icon" class="w-5 h-5" />
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 font-mono">
                                    {{ mod.status }}
                                </span>
                            </div>
                            <h3 class="font-bold text-slate-900 text-sm mb-1">{{ mod.name }}</h3>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ mod.desc }}</p>
                        </div>
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 font-medium">
                            <span class="flex items-center gap-1 text-slate-600">
                                <CheckCircle2 class="w-3.5 h-3.5 text-teal-600" />
                                Especificado v4
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500 font-medium">
            <p>BSDental v4 &copy; BSolutions — Plataforma Odontológica Multi-Tenant. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>
