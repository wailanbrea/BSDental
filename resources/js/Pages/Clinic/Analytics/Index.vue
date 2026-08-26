<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ref } from 'vue'
import { BarChart3, Download, TrendingUp, DollarSign, Users, Clock, AlertCircle, Calendar, Armchair } from 'lucide-vue-next'

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

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function filterPeriod() {
    router.get(appUrl('/analytics'), {
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
    <ClinicLayout>
        <Head title="Analytics & Métricas Gerenciales — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <BarChart3 class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Analytics & Métricas Gerenciales
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Control de producción clínica real, recaudación neta, costos directos y productividad
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-2 bg-white border border-[#BDC9C6] rounded-lg px-3 py-1.5 text-xs shadow-xs">
                        <Calendar class="w-3.5 h-3.5 text-[#505F76]" />
                        <input v-model="localStart" type="date" class="bg-transparent text-[#131B2E] border-none focus:outline-none font-mono text-xs" />
                        <span class="text-[#505F76]">hasta</span>
                        <input v-model="localEnd" type="date" class="bg-transparent text-[#131B2E] border-none focus:outline-none font-mono text-xs" />
                        <button class="ml-1 px-2.5 py-1 bg-[#005C55] hover:bg-[#004742] text-white font-bold rounded-md transition" @click="filterPeriod">
                            Filtrar
                        </button>
                    </div>

                    <button
                        class="flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg transition border border-[#BDC9C6] shadow-xs"
                        @click="exportCsv"
                    >
                        <Download class="w-3.5 h-3.5 text-[#505F76]" /> Exportar CSV
                    </button>
                </div>
            </div>

            <!-- Financial KPIs Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs font-semibold text-[#505F76]">
                        <span>Producción Clínica</span>
                        <TrendingUp class="w-4 h-4 text-[#005C55]" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">{{ formatMoney(kpis.production) }}</span>
                        <p class="text-[11px] text-[#505F76] mt-0.5">Valor de procedimientos realizados</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs font-semibold text-[#505F76]">
                        <span>Cobrado Neto</span>
                        <DollarSign class="w-4 h-4 text-emerald-700" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-emerald-700">{{ formatMoney(kpis.net_collected) }}</span>
                        <p class="text-[11px] text-[#505F76] mt-0.5">Cobros menos reembolsos</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs font-semibold text-[#505F76]">
                        <span>Margen de Contribución</span>
                        <TrendingUp class="w-4 h-4 text-blue-700" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular text-blue-800">{{ formatMoney(kpis.contribution_margin) }}</span>
                        <p class="text-[11px] text-[#505F76] mt-0.5">Producción menos costos e insumos</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                    <div class="flex items-center justify-between text-xs font-semibold text-[#505F76]">
                        <span>Cuentas por Cobrar (CxC)</span>
                        <AlertCircle class="w-4 h-4" :class="kpis.receivables > 0 ? 'text-[#BA1A1A]' : 'text-slate-400'" />
                    </div>
                    <div class="mt-3">
                        <span class="text-2xl font-bold font-data-tabular" :class="kpis.receivables > 0 ? 'text-[#BA1A1A]' : 'text-[#131B2E]'">
                            {{ formatMoney(kpis.receivables) }}
                        </span>
                        <p class="text-[11px] text-[#505F76] mt-0.5">Saldos pendientes de pacientes</p>
                    </div>
                </div>
            </div>

            <!-- Aging & Direct Costs Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Receivables Aging -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="border-b border-[#E2E8F0] pb-3">
                        <h2 class="font-section-title text-[#131B2E]">Antigüedad de Deuda (Aging CxC)</h2>
                        <p class="text-xs text-[#505F76]">Segmentación temporal de la cartera por cobrar</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">0-30 Días</span>
                            <span class="text-sm font-bold font-data-tabular text-emerald-700 mt-1 block">{{ formatMoney(receivablesAging.current_0_30) }}</span>
                        </div>
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">31-60 Días</span>
                            <span class="text-sm font-bold font-data-tabular text-blue-700 mt-1 block">{{ formatMoney(receivablesAging.aging_31_60) }}</span>
                        </div>
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">61-90 Días</span>
                            <span class="text-sm font-bold font-data-tabular text-amber-800 mt-1 block">{{ formatMoney(receivablesAging.aging_61_90) }}</span>
                        </div>
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">+90 Días</span>
                            <span class="text-sm font-bold font-data-tabular text-[#BA1A1A] mt-1 block">{{ formatMoney(receivablesAging.aging_over_90) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Direct Costs Summary -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="border-b border-[#E2E8F0] pb-3">
                        <h2 class="font-section-title text-[#131B2E]">Costos Directos del Periodo</h2>
                        <p class="text-xs text-[#505F76]">Insumos, comisiones médicas y trabajos de laboratorio</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">Insumos Clínicos</span>
                            <span class="text-sm font-bold font-data-tabular text-[#131B2E] mt-1 block">{{ formatMoney(kpis.direct_material_costs) }}</span>
                        </div>
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">Laboratorio Dental</span>
                            <span class="text-sm font-bold font-data-tabular text-[#131B2E] mt-1 block">{{ formatMoney(kpis.direct_lab_costs) }}</span>
                        </div>
                        <div class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                            <span class="text-[10px] text-[#505F76] font-bold uppercase block">Comisiones Médicas</span>
                            <span class="text-sm font-bold font-data-tabular text-[#131B2E] mt-1 block">{{ formatMoney(kpis.professional_commissions) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Doctor Productivity & Chair Occupancy -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Doctor Productivity -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="border-b border-[#E2E8F0] pb-3">
                        <h2 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <Users class="w-4 h-4 text-[#005C55]" /> Productividad por Odontólogo
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                <tr>
                                    <th class="px-3 py-2.5 font-semibold">Profesional</th>
                                    <th class="px-3 py-2.5 text-center font-semibold">Citas</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Producción</th>
                                    <th class="px-3 py-2.5 text-right font-semibold">Comisiones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#E2E8F0]">
                                <tr v-for="doc in doctorProductivity" :key="doc.id" class="hover:bg-[#F8FAFC] transition">
                                    <td class="px-3 py-2.5">
                                        <strong class="text-[#131B2E] block">{{ doc.name }}</strong>
                                        <span class="text-[10px] text-[#505F76]">{{ doc.specialty }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-data-tabular text-[#505F76]">{{ doc.completed_appointments }}</td>
                                    <td class="px-3 py-2.5 text-right font-data-tabular text-emerald-700 font-bold">{{ formatMoney(doc.production_value) }}</td>
                                    <td class="px-3 py-2.5 text-right font-data-tabular text-blue-700 font-bold">{{ formatMoney(doc.commissions_accrued) }}</td>
                                </tr>
                                <tr v-if="!doctorProductivity.length">
                                    <td colspan="4" class="px-3 py-6 text-center text-xs text-[#505F76]">
                                        No hay datos de productividad en el rango seleccionado.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Chair Occupancy -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="border-b border-[#E2E8F0] pb-3">
                        <h2 class="font-section-title text-[#131B2E] flex items-center gap-2">
                            <Clock class="w-4 h-4 text-[#005C55]" /> Ocupación de Sillones Clínicos
                        </h2>
                    </div>

                    <div class="space-y-2.5">
                        <div 
                            v-for="chair in chairOccupancy" 
                            :key="chair.id" 
                            class="p-3.5 bg-[#F8FAFC] rounded-xl border border-[#E2E8F0] flex items-center justify-between text-xs hover:border-[#BDC9C6] transition"
                        >
                            <div class="flex items-center gap-2.5">
                                <Armchair class="w-4 h-4 text-[#005C55]" />
                                <div>
                                    <strong class="text-[#131B2E] block">{{ chair.name }}</strong>
                                    <span class="text-[10px] text-[#505F76]">Sede: {{ chair.branch_name }}</span>
                                </div>
                            </div>
                            <div class="text-right font-mono">
                                <span class="text-[#005C55] font-bold block">{{ chair.total_appointments }} Citas</span>
                                <span class="text-[#505F76] text-[10px]">{{ chair.occupied_minutes }} min</span>
                            </div>
                        </div>

                        <div v-if="!chairOccupancy.length" class="p-6 text-center text-xs text-[#505F76]">
                            No hay registros de ocupación en el periodo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
