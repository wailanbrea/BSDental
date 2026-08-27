<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ref } from 'vue'
import { ArrowLeft, Printer, Wallet } from 'lucide-vue-next'

interface ChargeDetail {
    id: string
    charge_number: string
    patient_id: string
    patient_name: string
    patient_record: string
    patient_phone?: string
    concept: string
    total_amount: number
    paid_amount: number
    adjusted_amount: number
    balance_due: number
    days_old: number
    created_at: string
    due_date?: string
}

interface Bucket {
    label: string
    total: number
    charges: ChargeDetail[]
}

interface AgingReport {
    buckets: {
        current_30: Bucket
        aging_31_60: Bucket
        aging_61_90: Bucket
        over_90: Bucket
    }
    total_receivable: number
    total_charges_count: number
}

defineProps<{
    report: AgingReport
}>()

const activeBucketKey = ref<'current_30' | 'aging_31_60' | 'aging_61_90' | 'over_90'>('over_90')

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function triggerPrint() {
    window.print()
}
</script>

<template>
    <ClinicLayout>
        <Head title="Cuentas por Cobrar (CxC por Antigüedad) — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0] print:hidden">
                <div class="flex items-center gap-3">
                    <Link 
                        :href="appUrl('/dashboard')"
                        class="p-2 text-[#505F76] hover:text-[#131B2E] hover:bg-white rounded-lg border border-[#E2E8F0] transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E] flex items-center gap-2">
                            <Wallet class="w-6 h-6 text-[#BA1A1A]" />
                            <span>Cuentas por Cobrar (CxC por Antigüedad)</span>
                        </h1>
                        <p class="text-xs text-[#505F76] mt-0.5">
                            Segmentación por tramos de vencimiento: Corriente (0-30d), 31-60d, 61-90d y +90d
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-[#BDC9C6] hover:bg-[#F8FAFC] text-[#131B2E] font-medium text-xs rounded-lg transition shadow-xs"
                        @click="triggerPrint"
                    >
                        <Printer class="w-3.5 h-3.5" /> Imprimir Reporte
                    </button>
                </div>
            </div>

            <!-- 4 Aging Buckets Bento Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Bucket 1: 0 - 30 days -->
                <div 
                    :class="[
                        'p-5 rounded-xl border transition cursor-pointer flex flex-col justify-between shadow-xs',
                        activeBucketKey === 'current_30' ? 'bg-[#F2F3FF] border-[#005C55] ring-2 ring-[#005C55]/20' : 'bg-white border-[#E2E8F0]'
                    ]"
                    @click="activeBucketKey = 'current_30'"
                >
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-[#505F76]">0 - 30 días</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Corriente
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-bold font-data-tabular text-[#131B2E]">
                                {{ formatMoney(report.buckets.current_30.total) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-[#505F76] mt-3 pt-2 border-t border-[#E2E8F0]">
                        {{ report.buckets.current_30.charges.length }} cargos pendientes
                    </p>
                </div>

                <!-- Bucket 2: 31 - 60 days -->
                <div 
                    :class="[
                        'p-5 rounded-xl border transition cursor-pointer flex flex-col justify-between shadow-xs',
                        activeBucketKey === 'aging_31_60' ? 'bg-[#F2F3FF] border-[#005C55] ring-2 ring-[#005C55]/20' : 'bg-white border-[#E2E8F0]'
                    ]"
                    @click="activeBucketKey = 'aging_31_60'"
                >
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-[#505F76]">31 - 60 días</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                Moderado
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-bold font-data-tabular text-amber-900">
                                {{ formatMoney(report.buckets.aging_31_60.total) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-[#505F76] mt-3 pt-2 border-t border-[#E2E8F0]">
                        {{ report.buckets.aging_31_60.charges.length }} cargos pendientes
                    </p>
                </div>

                <!-- Bucket 3: 61 - 90 days -->
                <div 
                    :class="[
                        'p-5 rounded-xl border transition cursor-pointer flex flex-col justify-between shadow-xs',
                        activeBucketKey === 'aging_61_90' ? 'bg-[#F2F3FF] border-[#005C55] ring-2 ring-[#005C55]/20' : 'bg-white border-[#E2E8F0]'
                    ]"
                    @click="activeBucketKey = 'aging_61_90'"
                >
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-[#505F76]">61 - 90 días</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                Alto Riesgo
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-bold font-data-tabular text-orange-900">
                                {{ formatMoney(report.buckets.aging_61_90.total) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-[#505F76] mt-3 pt-2 border-t border-[#E2E8F0]">
                        {{ report.buckets.aging_61_90.charges.length }} cargos pendientes
                    </p>
                </div>

                <!-- Bucket 4: Over 90 days -->
                <div 
                    :class="[
                        'p-5 rounded-xl border transition cursor-pointer flex flex-col justify-between shadow-xs',
                        activeBucketKey === 'over_90' ? 'bg-[#FFDAD6]/40 border-[#BA1A1A] ring-2 ring-[#BA1A1A]/20' : 'bg-white border-[#E2E8F0]'
                    ]"
                    @click="activeBucketKey = 'over_90'"
                >
                    <div>
                        <div class="flex justify-between items-center">
                            <span class="text-xs font-semibold text-[#505F76]">+90 días</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FFDAD6] text-[#BA1A1A] border border-[#BA1A1A]/30">
                                Vencido Crítico
                            </span>
                        </div>
                        <div class="mt-3">
                            <span class="text-2xl font-bold font-data-tabular text-[#BA1A1A]">
                                {{ formatMoney(report.buckets.over_90.total) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-[11px] text-[#505F76] mt-3 pt-2 border-t border-[#E2E8F0]">
                        {{ report.buckets.over_90.charges.length }} cargos pendientes
                    </p>
                </div>
            </div>

            <!-- Bucket Detail Table -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex justify-between items-center pb-3 border-b border-[#E2E8F0]">
                    <div>
                        <h3 class="font-section-title text-[#131B2E]">
                            Detalle de Cargos: {{ report.buckets[activeBucketKey].label }}
                        </h3>
                        <p class="text-xs text-[#505F76]">
                            Total en este tramo: <strong class="text-[#131B2E] font-data-tabular">{{ formatMoney(report.buckets[activeBucketKey].total) }}</strong>
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table v-if="report.buckets[activeBucketKey].charges.length > 0" class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Nº Cargo</th>
                                <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                <th class="px-4 py-2.5 font-semibold">Concepto</th>
                                <th class="px-4 py-2.5 font-semibold">Fecha Emisión</th>
                                <th class="px-4 py-2.5 font-semibold text-center">Antigüedad</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Monto Total</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Saldo Pendiente</th>
                                <th class="px-4 py-2.5 font-semibold text-center print:hidden">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="c in report.buckets[activeBucketKey].charges" :key="c.id" class="hover:bg-[#F8FAFC] transition-colors h-11">
                                <td class="px-4 py-2 font-mono font-bold text-[#131B2E]">{{ c.charge_number }}</td>
                                <td class="px-4 py-2 font-medium text-[#131B2E]">
                                    <Link :href="appUrl(`/patients/${c.patient_id}`)" class="hover:text-[#005C55] hover:underline">
                                        {{ c.patient_name }}
                                    </Link>
                                    <span class="text-[10px] text-[#505F76] block font-mono">{{ c.patient_record }}</span>
                                </td>
                                <td class="px-4 py-2 text-[#505F76] truncate max-w-[200px]">{{ c.concept }}</td>
                                <td class="px-4 py-2 text-[#505F76] font-data-tabular">{{ c.created_at }}</td>
                                <td class="px-4 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                        {{ c.days_old }} días
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right font-data-tabular">{{ formatMoney(c.total_amount) }}</td>
                                <td class="px-4 py-2 text-right font-data-tabular font-bold text-[#BA1A1A]">{{ formatMoney(c.balance_due) }}</td>
                                <td class="px-4 py-2 text-center print:hidden">
                                    <Link 
                                        :href="appUrl(`/patients/${c.patient_id}/billing`)"
                                        class="px-2.5 py-1 text-xs font-semibold text-[#005C55] bg-[#A3FAEF]/30 hover:bg-[#A3FAEF]/60 rounded-md transition"
                                    >
                                        Cobrar / Ver Ficha
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="p-8 text-center text-xs text-[#505F76]">
                        No hay cargos en este tramo de antigüedad actualmente.
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
