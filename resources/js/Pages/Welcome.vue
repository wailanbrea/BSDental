<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { Activity, ArrowRight, Boxes, Calendar, DollarSign, ExternalLink, FileText, Layers, ShieldCheck, Sparkles, UserCheck } from 'lucide-vue-next'

interface Props {
    appName?: string
    version?: string
    phpVersion?: string
    laravelVersion?: string
    environment?: string
}

withDefaults(defineProps<Props>(), {
    appName: 'BSDental',
    version: '4.0.0-dev',
    phpVersion: '8.2 / 8.5',
    laravelVersion: '12.x / 13.x',
    environment: 'development'
})

const demoRoles = [
    {
        role: 'Directora / Owner',
        email: 'owner@bsdental.com',
        desc: 'Acceso total: clínico, finanzas, analítica, configuración y usuarios.',
        color: 'bg-teal-50 text-[#005C55] border-teal-200',
    },
    {
        role: 'Odontólogo General',
        email: 'doctor@bsdental.com',
        desc: 'Agenda quirúrgica, odontograma, evoluciones SOAP y presupuestos.',
        color: 'bg-blue-50 text-blue-700 border-blue-200',
    },
    {
        role: 'Recepcionista',
        email: 'recepcion@bsdental.com',
        desc: 'Gestión de citas, admisión de pacientes, CRM y enlaces WhatsApp.',
        color: 'bg-purple-50 text-purple-700 border-purple-200',
    },
    {
        role: 'Cajero / Cobranzas',
        email: 'cajero@bsdental.com',
        desc: 'Apertura/cierre de cajas, cobros multidivisa, arqueo y recibos.',
        color: 'bg-emerald-50 text-emerald-800 border-emerald-200',
    },
    {
        role: 'Jefe de Almacén',
        email: 'almacen@bsdental.com',
        desc: 'Kardex de insumos, compras de lotes y alertas de caducidad.',
        color: 'bg-amber-50 text-amber-800 border-amber-200',
    },
]

const modules = [
    { name: 'Multi-Tenancy Físico Aislado', desc: 'Database-per-tenant con Spatie y TenantContext centralizado.', icon: Layers, status: 'Listo' },
    { name: 'Seguridad ASVS 5.0 & RBAC', desc: 'Argon2id, 2FA, 5 roles con permisos PascalCase y auditoría inmutable.', icon: ShieldCheck, status: 'Listo' },
    { name: 'Historia Clínica 360 & Odontograma', desc: 'Evoluciones SOAP inmutables con enmiendas y odontograma interactivo FDI.', icon: FileText, status: 'Listo' },
    { name: 'Agenda Quirúrgica & Citas', desc: 'Turnos por sillón/profesional con estados en tiempo real.', icon: Calendar, status: 'Listo' },
    { name: 'Cajas, Facturación & CxC', desc: 'Arqueo ciego, reaperturas auditadas, notas de crédito y aging CxC.', icon: DollarSign, status: 'Listo' },
    { name: 'Kardex, Lotes & Laboratorio', desc: 'Trazabilidad FIFO de insumos, recepciones con control de calidad y re-trabajo.', icon: Boxes, status: 'Listo' },
]
</script>

<template>
    <Head title="Plataforma Odontológica SaaS — BSDental v4" />

    <div class="min-h-screen bg-[#F8FAFC] flex flex-col justify-between selection:bg-[#CCFBF1] selection:text-[#005C55]">
        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-[#E2E8F0] sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#005C55] to-[#004742] flex items-center justify-center text-white shadow-2xs">
                        <Activity class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-lg text-[#131B2E] tracking-tight">{{ appName }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#005C55]/10 text-[#005C55]">
                                v4 Multi-Tenant
                            </span>
                        </div>
                        <p class="text-xs text-[#505F76] font-medium">Dental Practice Management & Clinical Precision Platform</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <a
                        href="http://demo.localhost:8000/login"
                        class="flex items-center gap-1.5 px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-semibold text-xs rounded-lg transition shadow-xs"
                    >
                        Entrar a Clínica Demo <ArrowRight class="w-3.5 h-3.5" />
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Hero & Navigation Hub -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 space-y-8">
            <!-- Hero Card -->
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 sm:p-8 shadow-xs">
                <div class="max-w-3xl space-y-3">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-[#005C55]/10 text-[#005C55] border border-[#005C55]/20 text-xs font-bold">
                        <Sparkles class="w-4 h-4" />
                        BSDental v4 — Todos los Módulos Construidos y Verificados (97/97 Tests)
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-[#131B2E] tracking-tight leading-tight">
                        Sistema Clínico & ERP Odontológico de Alta Precisión
                    </h1>
                    <p class="text-sm text-[#505F76] leading-relaxed">
                        Arquitectura multi-inquilino con aislamiento <strong class="text-[#131B2E]">Database-per-Tenant</strong>. 
                        Diseñado con rigor clínico (invariantes financieras, inmutabilidad de historia clínica y control total de caja).
                    </p>
                </div>

                <!-- Launch Action Cards Grid -->
                <div class="mt-6 pt-6 border-t border-[#E2E8F0] grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-5 bg-[#F8FAFC] border border-[#BDC9C6] rounded-xl flex flex-col justify-between space-y-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-[#005C55]">Acceso Inquilino (Clínica Demo)</span>
                            <h3 class="font-bold text-base text-[#131B2E] mt-1">BSolutions Dental Clinic & Specialties</h3>
                            <p class="text-xs text-[#505F76] mt-1">
                                Consulta la agenda, historias clínicas, odontogramas, cajas, facturación y Kardex de insumos.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 pt-2">
                            <a
                                href="http://demo.localhost:8000/login"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition shadow-xs"
                            >
                                Iniciar Sesión (demo.localhost) <ExternalLink class="w-3.5 h-3.5" />
                            </a>
                            <a
                                href="http://demo.127.0.0.1.nip.io:8000/login"
                                class="inline-flex items-center gap-1.5 px-3 py-2 bg-white border border-[#BDC9C6] text-[#505F76] hover:text-[#131B2E] text-xs font-medium rounded-lg transition"
                                title="Enlace alternativo si tu DNS no resuelve localhost"
                            >
                                Enlace Alternativo (nip.io)
                            </a>
                        </div>
                    </div>

                    <div class="p-5 bg-[#F8FAFC] border border-[#BDC9C6] rounded-xl flex flex-col justify-between space-y-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-purple-700">Acceso Plataforma (SuperAdmin SaaS)</span>
                            <h3 class="font-bold text-base text-[#131B2E] mt-1">Panel Landlord de Gestión de Clínicas</h3>
                            <p class="text-xs text-[#505F76] mt-1">
                                Administra inquilinos, crea nuevas clínicas, asigna planes comerciales y ejecuta respaldos.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 pt-2">
                            <a
                                href="http://localhost:8000/platform/login"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-lg transition shadow-xs"
                            >
                                Panel Platform (admin@bsdental.io) <ExternalLink class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demo Credentials Hub -->
            <div class="bg-white rounded-2xl border border-[#E2E8F0] p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h2 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <UserCheck class="w-4 h-4 text-[#005C55]" /> Cuentas de Acceso de Prueba (Contraseña: <code class="font-mono text-[#005C55] font-bold">Password123!</code>)
                        </h2>
                        <p class="text-xs text-[#505F76]">Prueba los 5 perfiles precargados para verificar el aislamiento de permisos por rol</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                    <div 
                        v-for="user in demoRoles" 
                        :key="user.email"
                        class="p-3.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl flex flex-col justify-between space-y-2 hover:border-[#BDC9C6] transition"
                    >
                        <div>
                            <span :class="['px-2 py-0.5 rounded-md text-[10px] font-bold border inline-block', user.color]">
                                {{ user.role }}
                            </span>
                            <div class="font-mono font-bold text-xs text-[#131B2E] mt-2 select-all">
                                {{ user.email }}
                            </div>
                            <p class="text-[11px] text-[#505F76] mt-1 leading-snug">
                                {{ user.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modules Pillars Grid -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-section-title text-[#131B2E]">Módulos y Capacidades del Sistema</h2>
                    <span class="text-xs text-[#505F76]">100% integrados</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div 
                        v-for="mod in modules" 
                        :key="mod.name"
                        class="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-xs flex flex-col justify-between"
                    >
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-9 h-9 rounded-lg bg-[#005C55]/10 flex items-center justify-center text-[#005C55]">
                                    <component :is="mod.icon" class="w-5 h-5" />
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ mod.status }}
                                </span>
                            </div>
                            <h3 class="font-bold text-[#131B2E] text-xs mb-1">{{ mod.name }}</h3>
                            <p class="text-xs text-[#505F76] leading-relaxed">{{ mod.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-[#E2E8F0] py-4 text-center text-xs text-[#505F76] font-medium">
            <p>BSDental v4 &copy; BSolutions — Plataforma Odontológica Multi-Tenant de Alta Precisión.</p>
        </footer>
    </div>
</template>
