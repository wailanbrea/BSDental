<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Link2, Printer } from 'lucide-vue-next'

interface Charge {
    id: string
    charge_number: string
    concept: string
    total_amount: number
    paid_amount: number
    adjusted_amount: number
    balance_due: number
    status: string
    created_at: string
}

interface Payment {
    id: string
    payment_number: string
    total_amount: number
    allocated_amount: number
    unallocated_amount: number
    refunded_amount: number
    status: string
    paid_at: string
    splits: Array<{ method: string; amount: number; reference_code?: string }>
}

interface Adjustment {
    id: string
    credit_note_number: string
    type: string
    amount: number
    reason: string
    adjusted_at: string
    charge?: { charge_number: string }
}

interface StatementData {
    patient: {
        id: string
        record_number: string
        full_name: string
        identification_number?: string
        phone?: string
        email?: string
        address?: string
    }
    charges: Charge[]
    payments: Payment[]
    adjustments: Adjustment[]
    summary: {
        total_charged: number
        total_paid: number
        total_adjusted: number
        net_balance_due: number
        unallocated_credit: number
        payer_balance: number
        customer_credit: number
        saldo_a_favor: number
    }
}

const props = defineProps<{
    statement: StatementData
}>()

const page = usePage<{ auth?: { user?: { roles?: string[]; permissions?: string[] } } }>()
const canAllocateCredit = Boolean(
    page.props.auth?.user?.roles?.includes('Owner')
    || page.props.auth?.user?.permissions?.includes('payments.allocate'),
)

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
        <Head :title="`Estado de Cuenta — ${statement.patient.full_name}`" />

        <div class="space-y-6 max-w-5xl mx-auto">
            <!-- Navigation Toolbar -->
            <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0] print:hidden">
                <div class="flex items-center gap-3">
                    <Link 
                        :href="`/patients/${statement.patient.id}/billing`"
                        class="p-2 text-[#505F76] hover:text-[#131B2E] hover:bg-white rounded-lg border border-[#E2E8F0] transition"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div>
                        <h1 class="font-display-md text-xl font-bold text-[#131B2E]">
                            Estado de Cuenta del Paciente
                        </h1>
                        <p class="text-xs text-[#505F76]">
                            Historial consolidado de cargos, pagos recibidos, notas de crédito y saldo
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="canAllocateCredit && props.statement.summary.customer_credit > 0"
                        :href="`/patients/${props.statement.patient.id}/billing`"
                        class="flex items-center gap-1.5 px-4 py-2 bg-white border border-[#7FB8B1] text-[#005C55] font-semibold text-xs rounded-lg transition hover:bg-[#F1FAF8]"
                    >
                        <Link2 class="w-4 h-4" /> Aplicar saldo a un cargo
                    </Link>
                    <button
                        class="flex items-center gap-1.5 px-4 py-2 bg-[#005C55] hover:bg-[#00504A] text-white font-semibold text-xs rounded-lg transition shadow-xs"
                        @click="triggerPrint"
                    >
                        <Printer class="w-4 h-4" /> Imprimir Estado de Cuenta
                    </button>
                </div>
            </div>

            <!-- Statement Document Sheet (Printable) -->
            <div class="bg-white rounded-2xl border border-[#E2E8F0] shadow-sm p-6 sm:p-8 space-y-6">
                <!-- Document Header -->
                <div class="flex justify-between items-start pb-6 border-b border-[#E2E8F0]">
                    <div>
                        <div class="w-10 h-10 rounded-xl bg-[#005C55] text-white flex items-center justify-center font-bold mb-2">
                            BS
                        </div>
                        <h2 class="text-lg font-bold text-[#005C55]">BSDental Clinic Platform</h2>
                        <p class="text-xs text-[#505F76]">Servicios Odontológicos Integrales</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-bold text-[#005C55] uppercase tracking-wider block font-mono">ESTADO DE CUENTA</span>
                        <p class="text-xs text-[#505F76] mt-1">Fecha de emisión: {{ new Date().toLocaleDateString('es-ES') }}</p>
                    </div>
                </div>

                <!-- Patient & Balance Overview Bento -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl">
                    <div class="space-y-1 text-xs">
                        <span class="font-label-caps text-[#505F76] text-[10px]">DATOS DEL PACIENTE</span>
                        <p class="text-sm font-bold text-[#131B2E]">{{ statement.patient.full_name }}</p>
                        <p class="text-[#505F76]">Historia Clínica: <span class="font-mono font-bold text-[#131B2E]">{{ statement.patient.record_number }}</span></p>
                        <p v-if="statement.patient.identification_number" class="text-[#505F76]">Cédula/ID: <span class="font-mono text-[#131B2E]">{{ statement.patient.identification_number }}</span></p>
                        <p v-if="statement.patient.phone" class="text-[#505F76]">Teléfono: {{ statement.patient.phone }}</p>
                    </div>
                    <div class="flex flex-col justify-between border-t md:border-t-0 md:border-l border-[#E2E8F0] pt-4 md:pt-0 md:pl-6">
                        <span class="font-label-caps text-[#505F76] text-[10px]">CRÉDITO DEL PACIENTE</span>
                        <span class="mt-1 text-2xl font-bold font-data-tabular text-[#005C55]">{{ formatMoney(statement.summary.customer_credit) }}</span>
                        <p class="mt-2 text-[11px] text-[#505F76]">Saldo a favor disponible solo para aplicación manual.</p>
                    </div>

                    <div class="flex flex-col justify-between border-t md:border-t-0 md:border-l border-[#E2E8F0] pt-4 md:pt-0 md:pl-6">
                        <span class="font-label-caps text-[#505F76] text-[10px]">POSICIÓN NETA DEL PAGADOR</span>
                        <div class="mt-1">
                            <span 
                                class="text-3xl font-bold font-data-tabular"
                                :class="statement.summary.payer_balance > 0 ? 'text-[#BA1A1A]' : 'text-emerald-700'"
                            >
                                {{ formatMoney(statement.summary.payer_balance) }}
                            </span>
                        </div>
                        <div class="flex gap-4 mt-2 text-[11px] font-data-tabular">
                            <span class="text-[#505F76]">Total Cargos: <strong>{{ formatMoney(statement.summary.total_charged) }}</strong></span>
                            <span class="text-emerald-700">Total Pagado: <strong>{{ formatMoney(statement.summary.total_paid) }}</strong></span>
                            <span v-if="statement.summary.total_adjusted > 0" class="text-[#005C55]">Ajustes: <strong>{{ formatMoney(statement.summary.total_adjusted) }}</strong></span>
                            <span v-if="statement.summary.saldo_a_favor > 0" class="text-[#005C55]">Saldo a favor neto: <strong>{{ formatMoney(statement.summary.saldo_a_favor) }}</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Detailed Charges Section -->
                <div class="space-y-3">
                    <h3 class="font-section-title text-[#131B2E] text-sm">1. Cargos y Procedimientos Realizados</h3>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Nº Cargo</th>
                                <th class="px-3 py-2">Concepto</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2 text-right">Pagado</th>
                                <th class="px-3 py-2 text-right">Ajustes</th>
                                <th class="px-3 py-2 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="c in statement.charges" :key="c.id" class="h-9">
                                <td class="px-3 py-1.5 text-[#505F76] font-data-tabular">{{ c.created_at?.substring(0, 10) }}</td>
                                <td class="px-3 py-1.5 font-mono font-bold text-[#131B2E]">{{ c.charge_number }}</td>
                                <td class="px-3 py-1.5 text-[#131B2E]">{{ c.concept }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular">{{ formatMoney(c.total_amount) }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular text-emerald-700">{{ formatMoney(c.paid_amount) }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular text-[#005C55]">{{ formatMoney(c.adjusted_amount) }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular font-bold" :class="c.balance_due > 0 ? 'text-[#BA1A1A]' : 'text-emerald-700'">
                                    {{ formatMoney(c.balance_due) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Payments Received Section -->
                <div class="space-y-3">
                    <h3 class="font-section-title text-[#131B2E] text-sm">2. Pagos y Recibos Registrados</h3>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Nº Recibo</th>
                                <th class="px-3 py-2">Método(s)</th>
                                <th class="px-3 py-2 text-right">Monto Total</th>
                                <th class="px-3 py-2 text-right">Asignado</th>
                                <th class="px-3 py-2 text-right">Por Asignar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="p in statement.payments" :key="p.id" class="h-9">
                                <td class="px-3 py-1.5 text-[#505F76] font-data-tabular">{{ p.paid_at?.substring(0, 10) }}</td>
                                <td class="px-3 py-1.5 font-mono font-bold text-[#131B2E]">{{ p.payment_number }}</td>
                                <td class="px-3 py-1.5 text-[#505F76]">
                                    <span v-for="sp in p.splits" :key="sp.method" class="mr-2 inline-block">
                                        {{ sp.method }}: {{ formatMoney(sp.amount) }}
                                    </span>
                                </td>
                                <td class="px-3 py-1.5 text-right font-data-tabular font-bold text-emerald-700">{{ formatMoney(p.total_amount) }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular">{{ formatMoney(p.allocated_amount) }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular text-[#005C55]">{{ formatMoney(p.unallocated_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Credit Notes / Adjustments Section (FIN-05) -->
                <div v-if="statement.adjustments.length > 0" class="space-y-3">
                    <h3 class="font-section-title text-[#131B2E] text-sm">3. Notas de Crédito y Ajustes Autorizados</h3>
                    <table class="w-full text-left border-collapse text-xs">
                        <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                            <tr>
                                <th class="px-3 py-2">Fecha</th>
                                <th class="px-3 py-2">Nº Nota de Crédito</th>
                                <th class="px-3 py-2">Cargo Aplicado</th>
                                <th class="px-3 py-2">Motivo</th>
                                <th class="px-3 py-2 text-right">Monto Ajustado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E8F0]">
                            <tr v-for="adj in statement.adjustments" :key="adj.id" class="h-9">
                                <td class="px-3 py-1.5 text-[#505F76] font-data-tabular">{{ adj.adjusted_at?.substring(0, 10) }}</td>
                                <td class="px-3 py-1.5 font-mono font-bold text-[#005C55]">{{ adj.credit_note_number }}</td>
                                <td class="px-3 py-1.5 font-mono text-[#131B2E]">{{ adj.charge?.charge_number || 'N/A' }}</td>
                                <td class="px-3 py-1.5 text-[#505F76]">{{ adj.reason }}</td>
                                <td class="px-3 py-1.5 text-right font-data-tabular font-bold text-[#005C55]">{{ formatMoney(adj.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer / Stamp -->
                <div class="pt-6 border-t border-[#E2E8F0] flex justify-between items-end text-xs text-[#505F76]">
                    <div>
                        <p class="font-medium text-[#131B2E]">BSDental Cloud • Plataforma Multi-Tenant Segura</p>
                        <p class="text-[10px]">Documento generado electrónicamente con fines informativos y de conciliación clínica.</p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-[#131B2E]">Firma / Sello de Administración</p>
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
