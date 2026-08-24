<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { Truck, Plus, PackageCheck, Clock, CheckCircle2, RotateCcw, AlertCircle } from 'lucide-vue-next'

interface LaboratoryItem {
    id: string
    name: string
    contact_person: string | null
    phone: string | null
    orders_count: number
}

interface OrderItem {
    id: string
    order_number: string
    tooth_number: number | null
    work_description: string
    shade_guide: string | null
    status: 'draft' | 'ordered' | 'sent' | 'in_progress' | 'ready' | 'received' | 'delivered' | 'cancelled'
    estimated_cost: number
    final_cost: number
    payable_status: string
    due_date: string | null
    created_at: string
    parent_order_id?: string | null
    remake_reason?: string | null
    patient: { id: string; full_name: string; record_number: string }
    laboratory: { name: string }
}

defineProps<{
    laboratories: LaboratoryItem[]
    orders: OrderItem[]
}>()

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
    }).format(amount)
}

function statusBadge(status: string) {
    const map: Record<string, { label: string; class: string }> = {
        draft: { label: 'Borrador', class: 'bg-slate-100 text-slate-700 border-slate-200' },
        ordered: { label: 'Ordenado', class: 'bg-blue-50 text-blue-700 border-blue-200' },
        sent: { label: 'Enviado al Lab', class: 'bg-amber-50 text-amber-700 border-amber-200' },
        in_progress: { label: 'En Fabricación', class: 'bg-purple-50 text-purple-700 border-purple-200' },
        ready: { label: 'Listo', class: 'bg-teal-50 text-teal-700 border-teal-200' },
        received: { label: 'Recibido en Clínica', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
        delivered: { label: 'Instalado / Entregado', class: 'bg-green-100 text-green-800 border-green-300' },
        cancelled: { label: 'Cancelado', class: 'bg-rose-50 text-rose-700 border-rose-200' },
    }
    return map[status] || { label: status, class: 'bg-slate-100 text-slate-700 border-slate-200' }
}

function updateStatus(order: OrderItem, nextStatus: string) {
    useForm({
        status: nextStatus,
        final_cost: order.final_cost > 0 ? order.final_cost : order.estimated_cost,
    }).post(`/lab/orders/${order.id}/status`)
}
</script>

<template>
    <ClinicLayout>
        <Head title="Laboratorio Dental & Prótesis — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <Truck class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Laboratorio Dental & Prótesis
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Seguimiento de trabajos protésicos externos, control de calidad, re-trabajos y liquidación de costos
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link href="/dashboard" class="px-3.5 py-2 text-xs font-medium text-[#505F76] hover:text-[#131B2E] transition">
                        ← Dashboard
                    </Link>
                </div>
            </div>

            <!-- Laboratories Bento Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div 
                    v-for="lab in laboratories" 
                    :key="lab.id" 
                    class="p-5 bg-white border border-[#E2E8F0] rounded-xl shadow-xs flex items-center justify-between"
                >
                    <div>
                        <h2 class="font-bold text-sm text-[#131B2E]">{{ lab.name }}</h2>
                        <p class="text-xs text-[#505F76] mt-0.5">{{ lab.contact_person || 'Contacto general' }} • {{ lab.phone || 'S/T' }}</p>
                    </div>
                    <span class="px-2.5 py-1 bg-[#005C55]/10 text-[#005C55] border border-[#005C55]/20 rounded-full font-mono text-xs font-bold">
                        {{ lab.orders_count }} órdenes
                    </span>
                </div>
            </div>

            <!-- Orders Table & Workflow -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                    <div>
                        <h2 class="font-section-title text-[#131B2E]">Órdenes de Trabajo</h2>
                        <p class="text-xs text-[#505F76]">Control de estados, recepciones con inspección y trazabilidad</p>
                    </div>
                </div>

                <div v-if="orders.length === 0" class="text-xs text-[#505F76] py-8 text-center">
                    No hay órdenes de laboratorio registradas en este momento.
                </div>

                <div class="space-y-3">
                    <div 
                        v-for="ord in orders" 
                        :key="ord.id" 
                        class="p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl flex flex-col lg:flex-row lg:items-center justify-between gap-4 text-xs hover:border-[#BDC9C6] transition"
                    >
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono font-bold text-[#005C55] text-sm">{{ ord.order_number }}</span>
                                <span class="font-bold text-[#131B2E]">{{ ord.work_description }}</span>
                                <span v-if="ord.tooth_number" class="px-2 py-0.5 bg-white border border-[#E2E8F0] text-[#005C55] rounded-md font-mono font-bold text-[11px]">
                                    Pz {{ ord.tooth_number }}
                                </span>
                                <span v-if="ord.shade_guide" class="px-1.5 py-0.5 bg-amber-50 text-amber-800 border border-amber-200 rounded font-mono text-[11px]">
                                    Color: {{ ord.shade_guide }}
                                </span>
                                <span v-if="ord.parent_order_id" class="px-1.5 py-0.5 bg-purple-50 text-purple-700 border border-purple-200 rounded font-semibold text-[10px] flex items-center gap-1">
                                    <RotateCcw class="w-3 h-3" /> Re-trabajo
                                </span>
                            </div>
                            <p class="text-[#505F76]">
                                Paciente: <Link :href="`/patients/${ord.patient.id}`" class="font-semibold text-[#131B2E] hover:underline">{{ ord.patient.full_name }}</Link> ({{ ord.patient.record_number }}) • 
                                Laboratorio: <span class="font-medium text-[#131B2E]">{{ ord.laboratory.name }}</span>
                            </p>
                            <p v-if="ord.remake_reason" class="text-[11px] text-[#BA1A1A]">
                                Causa de re-trabajo: {{ ord.remake_reason }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                            <div class="text-right">
                                <div class="font-mono font-bold text-[#131B2E]">
                                    {{ formatMoney(ord.final_cost > 0 ? ord.final_cost : ord.estimated_cost) }}
                                </div>
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border inline-block mt-0.5', statusBadge(ord.status).class]">
                                    {{ statusBadge(ord.status).label }}
                                </span>
                            </div>

                            <!-- State Machine Actions -->
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-if="ord.status === 'draft' || ord.status === 'ordered'"
                                    class="px-3 py-1.5 bg-white border border-blue-300 text-blue-700 hover:bg-blue-50 rounded-lg font-medium text-xs transition"
                                    @click="updateStatus(ord, 'sent')"
                                >
                                    Enviar a Lab
                                </button>
                                <button
                                    v-if="ord.status === 'sent' || ord.status === 'in_progress'"
                                    class="px-3 py-1.5 bg-[#005C55] hover:bg-[#004742] text-white rounded-lg font-medium text-xs transition"
                                    @click="updateStatus(ord, 'received')"
                                >
                                    Recibir en Clínica
                                </button>
                                <button
                                    v-if="ord.status === 'received' || ord.status === 'ready'"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-xs transition"
                                    @click="updateStatus(ord, 'delivered')"
                                >
                                    Instalar en Paciente
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
