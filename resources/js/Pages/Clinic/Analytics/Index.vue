<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { BarChart3, Download, TrendingUp, DollarSign, Users, Clock, AlertCircle } from 'lucide-vue-next'

interface FinancialKpis {
    production: number
    gross_collected: number
    refunds: number
    net_collected: number
    receivables: number
    direct_material_costs: number
    direct_lab_costs: number
    professional_commissions: number
    contribution_margin: number
    net_cash_flow: number
}

interface DoctorProductivityItem {
    id: string
    name: string
    specialty: string
    completed_appointments: number
    completed_procedures: number
    production_value: number
    commissions_accrued: number
}

interface ReceivablesAgingData {
    current_0_30: number
    aging_31_60: number
    aging_61_90: number
    aging_over_90: number
    total_receivable: number
}

interface ChairOccupancyItem {
    id: string
    name: string
    branch_name: string
    total_appointments: number
    occupied_minutes: number
}

const props = defineProps<{
    startDate: string
    endDate: string
    kpis: FinancialKpis
    doctorProductivity: DoctorProductivityItem[]
    receivablesAging: ReceivablesAgingData
    chairOccupancy: ChairOccupancyItem[]
}>()

const localStart = ref(props.startDate)
const localEnd = ref(props.endDate)

function filterPeriod() {
    router.get('/analytics', {
        start_date: localStart.value,
        end_date: localEnd.value,
    }, {
        preserveState: true,
    })
}

function exportCsv() {
    window.location.href = `/analytics/export?start_date=${localStart.value}&end_date=${localEnd.value}`
}
</script>

<template>
    <Head title="Analytics & Métricas Gerenciales — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <BarChart3 class="w-6 h-6 text-teal-400" /> Analytics & Métricas Gerenciales
                    </h1>
                    <p class="text-sm text-slate-400">Control de producción real, cobrado neto, costos directos, margen y productividad médica</p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-xs">
                        <input v-model="localStart" type="date" class="bg-transparent text-white border-none focus:outline-none font-mono" />
                        <span class="text-slate-500">hasta</span>
                        <input v-model="localEnd" type="date" class="bg-transparent text-white border-none focus:outline-none font-mono" />
                        <button class="ml-2 px-2.5 py-1 bg-teal-500 text-slate-950 font-bold rounded-lg hover:bg-teal-400 transition" @click="filterPeriod">
                            Filtrar
                        </button>
                    </div>

                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg text-sm transition border border-slate-700"
                        @click="exportCsv"
                    >
                        <Download class="w-4 h-4" /> Exportar CSV
                    </button>
                </div>
            </div>

            <!-- Financial KPIs Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <span>Producción Clínica</span>
                        <TrendingUp class="w-4 h-4 text-teal-400" />
                    </div>
                    <div class="text-2xl font-mono font-black text-white">${{ kpis.production.toFixed(2) }}</div>
                    <p class="text-[11px] text-slate-500">Valor de procedimientos realizados</p>
                </div>

                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <span>Cobrado Neto</span>
                        <DollarSign class="w-4 h-4 text-emerald-400" />
                    </div>
                    <div class="text-2xl font-mono font-black text-emerald-400">${{ kpis.net_collected.toFixed(2) }}</div>
                    <p class="text-[11px] text-slate-500">Cobrado bruto (${{ kpis.gross_collected.toFixed(2) }}) - Reembolsos (${{ kpis.refunds.toFixed(2) }})</p>
                </div>

                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <span>Margen de Contribución</span>
                        <TrendingUp class="w-4 h-4 text-indigo-400" />
                    </div>
                    <div class="text-2xl font-mono font-black text-indigo-300">${{ kpis.contribution_margin.toFixed(2) }}</div>
                    <p class="text-[11px] text-slate-500">Producción menos insumos, lab y comisiones</p>
                </div>

                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-1 shadow-lg">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
                        <span>Cuentas por Cobrar (CxC)</span>
                        <AlertCircle class="w-4 h-4 text-amber-400" />
                    </div>
                    <div class="text-2xl font-mono font-black text-amber-400">${{ kpis.receivables.toFixed(2) }}</div>
                    <p class="text-[11px] text-slate-500">Saldos pendientes de pacientes</p>
                </div>
            </div>

            <!-- Aging & Direct Costs Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Receivables Aging -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Antigüedad de Deuda (Aging CxC)</h2>

                    <div class="grid grid-cols-4 gap-3 text-center">
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">0-30 Días</span>
                            <span class="text-base font-bold font-mono text-emerald-400">${{ receivablesAging.current_0_30.toFixed(2) }}</span>
                        </div>
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">31-60 Días</span>
                            <span class="text-base font-bold font-mono text-sky-400">${{ receivablesAging.aging_31_60.toFixed(2) }}</span>
                        </div>
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">61-90 Días</span>
                            <span class="text-base font-bold font-mono text-amber-400">${{ receivablesAging.aging_61_90.toFixed(2) }}</span>
                        </div>
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">+90 Días</span>
                            <span class="text-base font-bold font-mono text-rose-400">${{ receivablesAging.aging_over_90.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Direct Costs Summary -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Costos Directos del Periodo</h2>

                    <div class="grid grid-cols-3 gap-3 text-center">
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Insumos Clínicos</span>
                            <span class="text-base font-bold font-mono text-white">${{ kpis.direct_material_costs.toFixed(2) }}</span>
                        </div>
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Laboratorio Dental</span>
                            <span class="text-base font-bold font-mono text-white">${{ kpis.direct_lab_costs.toFixed(2) }}</span>
                        </div>
                        <div class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40">
                            <span class="text-[10px] text-slate-400 font-bold uppercase block">Comisiones Médicas</span>
                            <span class="text-base font-bold font-mono text-white">${{ kpis.professional_commissions.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Doctor Productivity & Chair Occupancy -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Doctor Productivity -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                        <Users class="w-4 h-4" /> Productividad por Odontólogo
                    </h2>

                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900/80 text-[10px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-700/60">
                            <tr>
                                <th class="px-3 py-2">Profesional</th>
                                <th class="px-3 py-2 text-center">Citas</th>
                                <th class="px-3 py-2 text-right">Producción</th>
                                <th class="px-3 py-2 text-right">Comisiones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/40">
                            <tr v-for="doc in doctorProductivity" :key="doc.id">
                                <td class="px-3 py-2.5">
                                    <strong class="text-white block">{{ doc.name }}</strong>
                                    <span class="text-[10px] text-slate-500">{{ doc.specialty }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center font-mono">{{ doc.completed_appointments }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-emerald-400 font-bold">${{ doc.production_value.toFixed(2) }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-indigo-300 font-bold">${{ doc.commissions_accrued.toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Chair Occupancy -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                        <Clock class="w-4 h-4" /> Ocupación de Sillones Clínicos
                    </h2>

                    <div class="space-y-3">
                        <div v-for="chair in chairOccupancy" :key="chair.id" class="p-4 bg-slate-900/80 rounded-2xl border border-slate-700/40 flex items-center justify-between text-xs">
                            <div>
                                <strong class="text-white block">{{ chair.name }}</strong>
                                <span class="text-[10px] text-slate-500">Sede: {{ chair.branch_name }}</span>
                            </div>
                            <div class="text-right font-mono">
                                <span class="text-teal-400 font-bold block">{{ chair.total_appointments }} Citas</span>
                                <span class="text-slate-400 text-[10px]">{{ chair.occupied_minutes }} Minutos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>