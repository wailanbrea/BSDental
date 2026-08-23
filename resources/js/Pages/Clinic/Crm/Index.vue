<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { MessageSquare, CheckCircle2, Clock, Plus, PhoneCall } from 'lucide-vue-next'

interface TaskSummary {
    id: string
    type: 'post_op' | 'no_show' | 'quote_pending' | 'treatment_incomplete' | 'periodic_recall'
    title: string
    due_date: string
    priority: 'low' | 'medium' | 'high'
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
    notes?: string
    patient?: { id: string; full_name: string; phone: string }
}

interface StageSummary {
    id: string
    name: string
    color: string
    profiles_count: number
}

interface NotificationSummary {
    id: string
    channel: string
    recipient: string
    status: string
    content: string
    scheduled_at: string
    patient?: { full_name: string }
}

defineProps<{
    tasks: TaskSummary[]
    stages: StageSummary[]
    notifications: NotificationSummary[]
}>()

const isTaskModal = ref(false)

const taskForm = useForm({
    patient_id: '',
    type: 'periodic_recall',
    title: '',
    due_date: new Date().toISOString().substring(0, 10),
    priority: 'medium',
    notes: '',
})

function submitTask() {
    taskForm.post('/crm/tasks', {
        onSuccess: () => {
            isTaskModal.value = false
            taskForm.reset()
        },
    })
}

function completeTask(id: string) {
    useForm({}).post(`/crm/tasks/${id}/complete`)
}
</script>

<template>
    <ClinicLayout>
<div class="clinical-precision-page">
    <Head title="CRM, Seguimiento Clínico & WhatsApp — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                        <MessageSquare class="w-6 h-6 text-teal-400" /> CRM, Recalls & WhatsApp Engine
                    </h1>
                    <p class="text-sm text-slate-400">Seguimiento post-operatorio, citas no atendidas, embudo de pacientes y recordatorios automáticos</p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="/dashboard" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">← Dashboard</a>
                    <button
                        class="flex items-center gap-2 px-4 py-2 bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold rounded-lg text-sm transition"
                        @click="isTaskModal = true"
                    >
                        <Plus class="w-4 h-4" /> Nueva Tarea de Seguimiento
                    </button>
                </div>
            </div>

            <!-- CRM Stages Pipeline Overview -->
            <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider">Embudo de Pacientes (Etapas CRM)</h2>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3">
                    <div v-for="stg in stages" :key="stg.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl text-center space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 truncate">{{ stg.name }}</div>
                        <div class="text-xl font-bold font-mono text-white">{{ stg.profiles_count }}</div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Follow-up Tasks -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                        <PhoneCall class="w-4 h-4" /> Tareas de Seguimiento Clínico & Recalls
                    </h2>

                    <div class="space-y-3">
                        <div v-if="tasks.length === 0" class="text-xs text-slate-500 italic py-4 text-center">
                            No hay tareas de seguimiento pendientes.
                        </div>

                        <div v-for="t in tasks" :key="t.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            t.type === 'post_op' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                                            t.type === 'no_show' ? 'bg-amber-500/20 text-amber-300 border-amber-500/30' :
                                            'bg-sky-500/20 text-sky-300 border-sky-500/30'
                                        ]"
                                        class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                    >
                                        {{ t.type }}
                                    </span>
                                    <span class="font-bold text-white">{{ t.title }}</span>
                                </div>

                                <button
                                    v-if="t.status !== 'completed'"
                                    class="flex items-center gap-1 text-[11px] font-bold text-teal-400 hover:text-teal-300 transition"
                                    @click="completeTask(t.id)"
                                >
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Completar
                                </button>
                                <span v-else class="text-[11px] font-bold text-emerald-400 flex items-center gap-1">
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Realizada
                                </span>
                            </div>

                            <div class="text-[11px] text-slate-400 flex items-center justify-between">
                                <span>Paciente: <strong class="text-white">{{ t.patient?.full_name }}</strong> ({{ t.patient?.phone }})</span>
                                <span>Vence: <strong class="text-teal-300 font-mono">{{ t.due_date }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Notifications Queue -->
                <div class="p-6 bg-slate-800/80 border border-slate-700/60 rounded-3xl space-y-4 shadow-xl">
                    <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                        <Clock class="w-4 h-4" /> Cola de Recordatorios WhatsApp (48h / 24h / 2h)
                    </h2>

                    <div class="space-y-3">
                        <div v-if="notifications.length === 0" class="text-xs text-slate-500 italic py-4 text-center">
                            No hay recordatorios programados en cola.
                        </div>

                        <div v-for="n in notifications" :key="n.id" class="p-4 bg-slate-900/80 border border-slate-700/40 rounded-2xl space-y-2 text-xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-teal-400 font-bold">{{ n.recipient }}</span>
                                    <span class="text-slate-400">({{ n.patient?.full_name }})</span>
                                </div>
                                <span
                                    :class="[
                                        n.status === 'responded' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' :
                                        n.status === 'sent' ? 'bg-sky-500/20 text-sky-300 border-sky-500/30' :
                                        n.status === 'cancelled' ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' :
                                        'bg-slate-800 text-slate-400 border-slate-700'
                                    ]"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border"
                                >
                                    {{ n.status }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-300 italic">{{ n.content }}</p>
                            <div class="text-[10px] text-slate-500 font-mono">Programado para: {{ n.scheduled_at }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Modal -->
            <div v-if="isTaskModal" class="p-6 bg-slate-800 border border-teal-500/30 rounded-2xl shadow-xl space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white">Programar Tarea de Seguimiento</h2>
                    <button class="text-slate-400 hover:text-white" @click="isTaskModal = false">×</button>
                </div>

                <form class="space-y-4" @submit.prevent="submitTask">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Tipo de Tarea *</label>
                            <select v-model="taskForm.type" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs">
                                <option value="post_op">Post-Operatorio</option>
                                <option value="no_show">Cita no Atendida (No-Show)</option>
                                <option value="quote_pending">Presupuesto Pendiente</option>
                                <option value="treatment_incomplete">Tratamiento Incompleto</option>
                                <option value="periodic_recall">Control Preventivo Periódico</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1">Fecha de Vencimiento *</label>
                            <input v-model="taskForm.due_date" type="date" required class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs font-mono" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Título de la Tarea *</label>
                        <input v-model="taskForm.title" type="text" required placeholder="Ej. Llamar para verificar estado tras exodoncia" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1">Observaciones</label>
                        <textarea v-model="taskForm.notes" rows="2" placeholder="Detalles clínicos o instrucciones" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-xs"></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="px-4 py-2 bg-slate-700 text-slate-300 text-xs font-medium rounded-lg hover:bg-slate-600" @click="isTaskModal = false">Cancelar</button>
                        <button type="submit" :disabled="taskForm.processing" class="px-4 py-2 bg-teal-500 text-slate-950 text-xs font-bold rounded-lg hover:bg-teal-400">Crear Tarea</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
