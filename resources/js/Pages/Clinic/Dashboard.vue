<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { 
    Calendar, 
    UserCheck, 
    CreditCard, 
    Wallet, 
    TrendingUp, 
    TrendingDown, 
    AlertTriangle, 
    Clock, 
    Users, 
    Package, 
    Plus, 
    ArrowRight,
    Building2,
    FlaskConical
} from 'lucide-vue-next'

interface Props {
    clinic: {
        name: string
        trade_name: string
        currency: string
        timezone: string
    }
    user: {
        id: string
        name: string
        email: string
        phone?: string | null
    }
    kpis: {
        appointments_today: number
        appointments_trend: string
        patients_attended_today: number
        attended_trend: string
        net_collected_today: number
        collected_trend: string
        accounts_receivable: number
    }
    today_appointments: Array<{
        id: string
        time: string
        patient_id: string
        patient_name: string
        patient_record: string
        reason: string
        doctor_name: string
        room_name: string
        status: string
        duration_minutes: number
    }>
    financial_chart: Array<{
        day: string
        date: string
        production: number
        collected: number
    }>
    alerts: {
        overdue_accounts_count: number
        low_stock_count: number
        pending_lab_orders_count: number
    }
    branches: Array<{
        id: string
        name: string
        is_main: boolean
    }>
    selected_branch_id?: string | null
}

const props = defineProps<Props>()

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.clinic.currency || 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function onBranchChange(e: Event) {
    const target = e.target as HTMLSelectElement
    router.get('/dashboard', { branch_id: target.value }, { preserveState: true })
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'waiting':
            return { label: 'En espera', class: 'bg-[#EAEDFF] text-[#0047BF] border-[#D0E1FB]' }
        case 'confirmed':
            return { label: 'Confirmada', class: 'bg-[#A3FAEF]/30 text-[#005C55] border-[#80D5CB]' }
        case 'in_progress':
            return { label: 'En atención', class: 'bg-amber-100 text-amber-800 border-amber-300' }
        case 'completed':
            return { label: 'Completada', class: 'bg-emerald-100 text-emerald-800 border-emerald-300' }
        case 'cancelled':
            return { label: 'Cancelada', class: 'bg-rose-100 text-rose-800 border-rose-300' }
        default:
            return { label: 'Programada', class: 'bg-[#F2F3FF] text-[#505F76] border-[#E2E8F0]' }
    }
}

const currentDateFormatted = new Intl.DateTimeFormat('es-ES', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long', 
    year: 'numeric' 
}).format(new Date())
</script>

<template>
    <ClinicLayout>
        <Head :title="`${clinic.trade_name} — Dashboard`" />

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="font-display-md text-2xl font-bold text-[#131B2E]">
                        Buenos días, {{ user.name }}
                    </h2>
                    <p class="text-xs text-[#505F76] mt-1 capitalize">
                        Resumen de {{ clinic.trade_name }} • <span class="font-medium text-[#131B2E]">{{ currentDateFormatted }}</span>
                    </p>
                </div>

                <!-- Filters toolbar -->
                <div class="flex items-center gap-3">
                    <select 
                        :value="selected_branch_id || ''"
                        @change="onBranchChange"
                        class="bg-white border border-[#BDC9C6] text-[#131B2E] rounded-lg px-3 py-1.5 text-xs font-medium focus:border-[#005C55] focus:ring-1 focus:ring-[#005C55] transition"
                    >
                        <option value="">Todas las Sedes</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>

                    <div class="px-3 py-1.5 bg-[#F2F3FF] text-[#005C55] border border-[#BDC9C6] rounded-lg text-xs font-bold">
                        Hoy
                    </div>
                </div>
            </div>

            <!-- 4 Top KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- KPI 1: Citas Hoy -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-semibold text-[#505F76]">Citas hoy</span>
                        <div class="w-8 h-8 rounded-lg bg-[#F2F3FF] text-[#005C55] flex items-center justify-center">
                            <Calendar class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ kpis.appointments_today }}</span>
                        <span class="flex items-center text-[#005C55] text-xs font-semibold">
                            <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.appointments_trend }}
                        </span>
                    </div>
                </div>

                <!-- KPI 2: Pacientes Atendidos -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-semibold text-[#505F76]">Pacientes atendidos</span>
                        <div class="w-8 h-8 rounded-lg bg-[#F2F3FF] text-[#005C55] flex items-center justify-center">
                            <UserCheck class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ kpis.patients_attended_today }}</span>
                        <span class="flex items-center text-[#0047BF] text-xs font-semibold">
                            <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.attended_trend }}
                        </span>
                    </div>
                </div>

                <!-- KPI 3: Cobrado Neto Hoy -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-semibold text-[#505F76]">Cobrado neto hoy</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                            <CreditCard class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ formatMoney(kpis.net_collected_today) }}</span>
                        <span class="flex items-center text-emerald-700 text-xs font-semibold">
                            <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.collected_trend }}
                        </span>
                    </div>
                </div>

                <!-- KPI 4: Cuentas por Cobrar (CxC) -->
                <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-semibold text-[#505F76]">Cuentas por cobrar</span>
                        <div class="w-8 h-8 rounded-lg bg-[#FFDAD6]/40 text-[#BA1A1A] flex items-center justify-center">
                            <Wallet class="w-4 h-4" />
                        </div>
                    </div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-[#BA1A1A] font-data-tabular">{{ formatMoney(kpis.accounts_receivable) }}</span>
                    </div>
                </div>
            </div>

            <!-- Main 2-Column Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left 2-Cols: Agenda & Financial Comparison -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Agenda de Hoy Card -->
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                        <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                            <div class="flex items-center gap-2">
                                <Clock class="w-4 h-4 text-[#005C55]" />
                                <h3 class="font-section-title text-[#131B2E]">Agenda de hoy</h3>
                            </div>
                            <Link href="/appointments" class="text-xs font-bold text-[#005C55] hover:underline flex items-center gap-1">
                                Ver todo <ArrowRight class="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        <div class="overflow-x-auto">
                            <table v-if="today_appointments.length > 0" class="w-full text-left border-collapse">
                                <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                    <tr>
                                        <th class="px-4 py-2.5 font-semibold">Hora</th>
                                        <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                        <th class="px-4 py-2.5 font-semibold">Motivo</th>
                                        <th class="px-4 py-2.5 font-semibold">Doctor</th>
                                        <th class="px-4 py-2.5 font-semibold text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                    <tr 
                                        v-for="app in today_appointments" 
                                        :key="app.id"
                                        class="hover:bg-[#F8FAFC] transition-colors h-11"
                                    >
                                        <td class="px-4 py-2 font-data-tabular font-bold text-[#131B2E]">{{ app.time }}</td>
                                        <td class="px-4 py-2 font-medium text-[#131B2E]">
                                            <Link :href="`/patients/${app.patient_id}`" class="hover:text-[#005C55] hover:underline">
                                                {{ app.patient_name }}
                                            </Link>
                                        </td>
                                        <td class="px-4 py-2 text-[#505F76] truncate max-w-[180px]">{{ app.reason }}</td>
                                        <td class="px-4 py-2 text-[#505F76]">{{ app.doctor_name }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span 
                                                :class="[
                                                    'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                                    getStatusBadge(app.status).class
                                                ]"
                                            >
                                                {{ getStatusBadge(app.status).label }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                No hay citas programadas para el día de hoy.
                            </div>
                        </div>
                    </div>

                    <!-- Financial Production vs Collections Chart -->
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                        <div class="flex justify-between items-center mb-4">
                            <div>
                                <h3 class="font-section-title text-[#131B2E]">Finanzas — Producción vs Cobrado</h3>
                                <p class="text-xs text-[#505F76] mt-0.5">Últimos 7 días de actividad clínica</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs font-semibold">
                                <span class="flex items-center gap-1.5 text-[#005C55]">
                                    <span class="w-2.5 h-2.5 rounded-full bg-[#005C55]"></span> Producción
                                </span>
                                <span class="flex items-center gap-1.5 text-emerald-700">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Cobrado
                                </span>
                            </div>
                        </div>

                        <!-- Visual Bar Chart -->
                        <div class="grid grid-cols-7 gap-3 pt-4 border-t border-[#E2E8F0]">
                            <div 
                                v-for="d in financial_chart" 
                                :key="d.date"
                                class="flex flex-col items-center gap-2"
                            >
                                <div class="w-full h-36 bg-[#F8FAFC] rounded-lg flex items-end justify-center gap-1.5 p-1.5 border border-[#E2E8F0]">
                                    <!-- Production bar -->
                                    <div 
                                        class="w-3 bg-[#005C55] rounded-t transition-all duration-300"
                                        :style="{ height: `${Math.min(100, Math.max(10, d.production / 10))}%` }"
                                        :title="`Producción: ${formatMoney(d.production)}`"
                                    ></div>
                                    <!-- Collected bar -->
                                    <div 
                                        class="w-3 bg-emerald-500 rounded-t transition-all duration-300"
                                        :style="{ height: `${Math.min(100, Math.max(10, d.collected / 10))}%` }"
                                        :title="`Cobrado: ${formatMoney(d.collected)}`"
                                    ></div>
                                </div>
                                <span class="font-label-caps text-[10px] text-[#505F76]">{{ d.day }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right 1-Col: Alerts & Quick Shortcuts -->
                <div class="space-y-6">
                    <!-- Requieren Atención (Operational Alerts) -->
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                        <h3 class="font-section-title text-[#131B2E] mb-4 flex items-center gap-2">
                            <AlertTriangle class="w-4 h-4 text-[#BA1A1A]" />
                            <span>Requieren atención</span>
                        </h3>

                        <div class="space-y-3">
                            <!-- Alert 1: CxC Vencidas -->
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#FFDAD6]/30 border border-[#BA1A1A]/30">
                                <Wallet class="w-4 h-4 text-[#BA1A1A] shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-[#131B2E]">{{ alerts.overdue_accounts_count }} cuentas por cobrar</p>
                                    <p class="text-[11px] text-[#505F76] mt-0.5">Saldos pendientes de cobro a pacientes.</p>
                                </div>
                                <Link href="/patients" class="text-xs font-bold text-[#BA1A1A] hover:underline shrink-0">
                                    Revisar
                                </Link>
                            </div>

                            <!-- Alert 2: Stock Bajo -->
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                <Package class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-[#131B2E]">{{ alerts.low_stock_count }} insumos bajo mínimo</p>
                                    <p class="text-[11px] text-[#505F76] mt-0.5">Alerta de reposición en almacén.</p>
                                </div>
                                <Link href="/inventory" class="text-xs font-bold text-amber-800 hover:underline shrink-0">
                                    Kardex
                                </Link>
                            </div>

                            <!-- Alert 3: Laboratorio Dental -->
                            <div class="flex items-start gap-3 p-3 rounded-lg bg-[#F2F3FF] border border-[#D0E1FB]">
                                <FlaskConical class="w-4 h-4 text-[#0047BF] shrink-0 mt-0.5" />
                                <div class="flex-1">
                                    <p class="text-xs font-bold text-[#131B2E]">{{ alerts.pending_lab_orders_count }} órdenes de prótesis</p>
                                    <p class="text-[11px] text-[#505F76] mt-0.5">Trabajos en proceso con laboratorio.</p>
                                </div>
                                <Link href="/lab" class="text-xs font-bold text-[#0047BF] hover:underline shrink-0">
                                    Órdenes
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Shortcuts Card -->
                    <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                        <h3 class="font-section-title text-[#131B2E] mb-4">Accesos Rápidos</h3>

                        <div class="grid grid-cols-2 gap-2.5">
                            <Link 
                                href="/appointments" 
                                class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                            >
                                <Calendar class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                <span class="text-xs font-bold text-[#131B2E]">Nueva Cita</span>
                            </Link>

                            <Link 
                                href="/patients/create" 
                                class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                            >
                                <Users class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                <span class="text-xs font-bold text-[#131B2E]">Nuevo Paciente</span>
                            </Link>

                            <Link 
                                href="/cash-registers" 
                                class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                            >
                                <CreditCard class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                <span class="text-xs font-bold text-[#131B2E]">Abrir Caja</span>
                            </Link>

                            <Link 
                                href="/analytics" 
                                class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                            >
                                <TrendingUp class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                <span class="text-xs font-bold text-[#131B2E]">Analítica KPI</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
