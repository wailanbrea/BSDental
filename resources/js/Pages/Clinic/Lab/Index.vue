<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Truck } from 'lucide-vue-next'

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
    patient: { id: string; full_name: string; record_number: string }
    laboratory: { name: string }
}

defineProps<{
    laboratories: LaboratoryItem[]
    orders: OrderItem[]
}>()

function updateStatus(order: OrderItem, nextStatus: string) {
    useForm({
        status: nextStatus,
        final_cost: order.final_cost > 0 ? order.final_cost : order.estimated_cost,
    }).post(`/lab/orders/${order.id}/status`)
}
</script>

<template>
    <ClinicLayout>
<div class="clinical-precision-page">
    <Head title="Laboratorio Dental & Prótesis — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <Truck class="w-6 h-6 text-teal-400" /> Laboratorio Dental & Prótesis
                    </h1>
                    <p class="text-sm text-slate-400">Seguimiento de trabajos externos, coronas, prótesis y costos de laboratorio</p>
                </div>

                <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
            </div>

            <!-- Labs Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div v-for="lab in laboratories" :key="lab.id" class="p-5 bg-slate-800/80 border border-slate-700/60 rounded-2xl flex items-center justify-between shadow-lg">
                    <div>
                        <h2 class="font-bold text-white text-base">{{ lab.name }}</h2>
                        <p class="text-xs text-slate-400">{{ lab.contact_person || 'Contacto general' }} | {{ lab.phone || 'S/T' }}</p>
                    </div>
                    <span class="px-3 py-1 bg-teal-500/10 text-teal-400 border border-teal-500/20 rounded-full font-mono text-xs font-bold">
                        {{ lab.orders_count }} órdenes
                    </span>
                </div>
            </div>

            <!-- Orders Table & Workflow -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Órdenes de Trabajo</h2>

                <div v-if="orders.length === 0" class="text-xs text-slate-500 py-6 text-center">
                    No hay órdenes de laboratorio registradas.
                </div>

                <div class="space-y-3">
                    <div v-for="ord in orders" :key="ord.id" class="p-5 bg-slate-900/90 border border-slate-700/50 rounded-2xl flex items-center justify-between text-xs">
                        <div class="space-y-1">
                            <div class="flex items-center gap-3">
                                <span class="font-mono font-bold text-teal-400 text-sm">{{ ord.order_number }}</span>
                                <span class="text-white font-bold">{{ ord.work_description }}</span>
                                <span v-if="ord.tooth_number" class="px-2 py-0.5 bg-slate-800 text-teal-300 rounded font-mono font-bold">
                                    Pz {{ ord.tooth_number }}
                                </span>
                                <span v-if="ord.shade_guide" class="text-amber-400 font-mono">({{ ord.shade_guide }})</span>
                            </div>
                            <p class="text-slate-400">
                                Paciente: <span class="text-white font-semibold">{{ ord.patient.full_name }}</span> ({{ ord.patient.record_number }}) | 
                                Laboratorio: <span class="text-slate-200">{{ ord.laboratory.name }}</span>
                            </p>
                        </div>

                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="font-mono font-bold text-white">${{ (ord.final_cost > 0 ? ord.final_cost : ord.estimated_cost).toFixed(2) }}</div>
                                <span
:class="[
                                    ord.status === 'delivered' ? 'text-emerald-400' :
                                    ord.status === 'ready' || ord.status === 'received' ? 'text-teal-400' : 'text-amber-400'
                                ]" class="font-bold uppercase text-[10px]"
>
                                    {{ ord.status }}
                                </span>
                            </div>

                            <!-- State Machine Actions -->
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-if="ord.status === 'draft' || ord.status === 'ordered'"
                                    class="px-2.5 py-1 bg-sky-500/20 text-sky-300 border border-sky-500/30 rounded-lg hover:bg-sky-500/30 font-bold"
                                    @click="updateStatus(ord, 'sent')"
                                >
                                    Enviar a Lab
                                </button>
                                <button
                                    v-if="ord.status === 'sent' || ord.status === 'in_progress'"
                                    class="px-2.5 py-1 bg-teal-500/20 text-teal-300 border border-teal-500/30 rounded-lg hover:bg-teal-500/30 font-bold"
                                    @click="updateStatus(ord, 'received')"
                                >
                                    Recibir en Clínica
                                </button>
                                <button
                                    v-if="ord.status === 'received' || ord.status === 'ready'"
                                    class="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-lg font-bold"
                                    @click="updateStatus(ord, 'delivered')"
                                >
                                    Entregar / Instalar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
