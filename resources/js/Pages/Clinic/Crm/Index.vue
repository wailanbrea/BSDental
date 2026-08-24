<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Check, CheckCircle2, Clock, MessageSquare, PhoneCall, Plus, X } from 'lucide-vue-next'

interface TaskSummary {
    id: string
    type: 'post_op' | 'no_show' | 'quote_pending' | 'treatment_incomplete' | 'periodic_recall'
    title: string
    due_date: string
    priority: 'low' | 'medium' | 'high'
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
    notes?: string
    patient?: { id: string; full_name: string; phone: string; record_number?: string }
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

function typeBadge(type: string) {
    const map: Record<string, { label: string; class: string }> = {
        post_op: { label: 'Post-Operatorio', class: 'bg-rose-50 text-rose-700 border-rose-200' },
        no_show: { label: 'No Asistió (No-Show)', class: 'bg-amber-50 text-amber-700 border-amber-200' },
        quote_pending: { label: 'Presupuesto Pendiente', class: 'bg-blue-50 text-blue-700 border-blue-200' },
        treatment_incomplete: { label: 'Tratamiento Incompleto', class: 'bg-purple-50 text-purple-700 border-purple-200' },
        periodic_recall: { label: 'Control Periódico', class: 'bg-teal-50 text-teal-700 border-teal-200' },
    }
    return map[type] || { label: type, class: 'bg-slate-100 text-slate-700 border-slate-200' }
}

function notificationBadge(status: string) {
    const map: Record<string, { label: string; class: string }> = {
        scheduled: { label: 'Programado', class: 'bg-slate-100 text-slate-700 border-slate-200' },
        queued: { label: 'En Cola', class: 'bg-blue-50 text-blue-700 border-blue-200' },
        sent: { label: 'Enviado', class: 'bg-sky-50 text-sky-700 border-sky-200' },
        delivered: { label: 'Entregado', class: 'bg-teal-50 text-teal-700 border-teal-200' },
        responded: { label: 'Respondido (Confirmado)', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
        cancelled: { label: 'Cancelado', class: 'bg-rose-50 text-rose-700 border-rose-200' },
        failed: { label: 'Fallido', class: 'bg-red-50 text-red-700 border-red-200' },
    }
    return map[status] || { label: status, class: 'bg-slate-100 text-slate-700 border-slate-200' }
}

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
        <Head title="CRM, Seguimiento Clínico & WhatsApp — BSDental" />

        <div class="space-y-6">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <MessageSquare class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            CRM, Recalls & Motor de WhatsApp
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Seguimiento post-operatorio, reactivación de pacientes, pipeline comercial y recordatorios automáticos
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        class="flex items-center gap-1.5 px-3.5 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-medium text-xs rounded-lg transition shadow-xs"
                        @click="isTaskModal = true"
                    >
                        <Plus class="w-3.5 h-3.5" /> Nueva Tarea de Seguimiento
                    </button>
                </div>
            </div>

            <!-- CRM Stages Pipeline Bento Overview -->
            <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                <div class="border-b border-[#E2E8F0] pb-3">
                    <h2 class="font-section-title text-[#131B2E]">Embudo Comercial & Fidelización (Etapas CRM)</h2>
                    <p class="text-xs text-[#505F76]">Distribución de pacientes por etapa de tratamiento y oportunidad</p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                    <div 
                        v-for="stg in stages" 
                        :key="stg.id" 
                        class="p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl text-center space-y-1 hover:border-[#BDC9C6] transition"
                    >
                        <div class="text-[10px] font-bold uppercase tracking-wider text-[#505F76] truncate" :title="stg.name">
                            {{ stg.name }}
                        </div>
                        <div class="text-xl font-bold font-data-tabular text-[#005C55]">
                            {{ stg.profiles_count }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Follow-up Tasks -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                        <div>
                            <h2 class="font-section-title text-[#131B2E] flex items-center gap-2">
                                <PhoneCall class="w-4 h-4 text-[#005C55]" /> Tareas de Seguimiento Clínico
                            </h2>
                            <p class="text-xs text-[#505F76]">Post-operatorios, reactivaciones y presupuestos en evaluación</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-if="tasks.length === 0" class="text-xs text-[#505F76] italic py-6 text-center">
                            No hay tareas de seguimiento pendientes.
                        </div>

                        <div 
                            v-for="t in tasks" 
                            :key="t.id" 
                            class="p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl space-y-2 text-xs hover:border-[#BDC9C6] transition"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', typeBadge(t.type).class]">
                                        {{ typeBadge(t.type).label }}
                                    </span>
                                    <span class="font-bold text-[#131B2E]">{{ t.title }}</span>
                                </div>

                                <button
                                    v-if="t.status !== 'completed'"
                                    class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-[#005C55] hover:text-[#004742] bg-[#005C55]/10 hover:bg-[#005C55]/20 rounded-lg transition"
                                    @click="completeTask(t.id)"
                                >
                                    <Check class="w-3.5 h-3.5" /> Completar
                                </button>
                                <span v-else class="text-xs font-bold text-emerald-700 flex items-center gap-1">
                                    <CheckCircle2 class="w-3.5 h-3.5" /> Realizada
                                </span>
                            </div>

                            <div class="text-[11px] text-[#505F76] flex items-center justify-between">
                                <span>
                                    Paciente: <strong class="text-[#131B2E]">{{ t.patient?.full_name }}</strong> ({{ t.patient?.phone || 'Sin tel.' }})
                                </span>
                                <span>Vence: <strong class="text-[#005C55] font-mono">{{ t.due_date }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- WhatsApp Notifications Queue -->
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-[#E2E8F0] pb-3">
                        <div>
                            <h2 class="font-section-title text-[#131B2E] flex items-center gap-2">
                                <Clock class="w-4 h-4 text-[#005C55]" /> Cola de Recordatorios WhatsApp
                            </h2>
                            <p class="text-xs text-[#505F76]">Mensajería automática de 48h, 24h y 2h antes de la cita</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-if="notifications.length === 0" class="text-xs text-[#505F76] italic py-6 text-center">
                            No hay recordatorios programados en cola.
                        </div>

                        <div 
                            v-for="n in notifications" 
                            :key="n.id" 
                            class="p-4 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl space-y-2 text-xs hover:border-[#BDC9C6] transition"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[#005C55] font-bold">{{ n.recipient }}</span>
                                    <span class="text-[#505F76]">({{ n.patient?.full_name || 'Paciente' }})</span>
                                </div>
                                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', notificationBadge(n.status).class]">
                                    {{ notificationBadge(n.status).label }}
                                </span>
                            </div>
                            <p class="text-[11px] text-[#131B2E] bg-white p-2 rounded-lg border border-[#E2E8F0]">
                                "{{ n.content }}"
                            </p>
                            <div class="text-[10px] text-[#505F76] font-mono">Programado para: {{ n.scheduled_at }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Task Modal -->
            <div v-if="isTaskModal" class="fixed inset-0 z-50 grid place-items-center bg-[#0F172A]/40 p-4 backdrop-blur-xs" @click.self="isTaskModal = false">
                <div class="w-full max-w-lg bg-white rounded-2xl border border-[#E2E8F0] shadow-2xl p-6 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
                        <div class="flex items-center gap-2">
                            <span class="p-1 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                                <Plus class="w-4 h-4" />
                            </span>
                            <h2 class="font-bold text-sm text-[#131B2E]">Programar Tarea de Seguimiento</h2>
                        </div>
                        <button class="text-[#505F76] hover:text-[#131B2E]" @click="isTaskModal = false">
                            <X class="w-4 h-4" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitTask">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                            <div>
                                <label class="block text-xs font-semibold text-[#505F76] mb-1">Tipo de Tarea *</label>
                                <select v-model="taskForm.type" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                    <option value="post_op">Post-Operatorio</option>
                                    <option value="no_show">Cita no Atendida (No-Show)</option>
                                    <option value="quote_pending">Presupuesto Pendiente</option>
                                    <option value="treatment_incomplete">Tratamiento Incompleto</option>
                                    <option value="periodic_recall">Control Preventivo Periódico</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-[#505F76] mb-1">Fecha de Vencimiento *</label>
                                <input v-model="taskForm.due_date" type="date" required class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs font-mono focus:bg-white focus:border-[#005C55]" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Título de la Tarea *</label>
                            <input v-model="taskForm.title" type="text" required placeholder="Ej. Llamar para verificar estado tras exodoncia" class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#505F76] mb-1">Observaciones</label>
                            <textarea v-model="taskForm.notes" rows="2" placeholder="Detalles clínicos o instrucciones para la recepcionista..." class="w-full px-3 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]"></textarea>
                        </div>

                        <div class="flex justify-end gap-2 pt-3 border-t border-[#E2E8F0]">
                            <button type="button" class="px-4 py-2 bg-white border border-[#BDC9C6] text-[#505F76] text-xs font-medium rounded-lg hover:bg-[#F8FAFC]" @click="isTaskModal = false">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="taskForm.processing" class="px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white text-xs font-bold rounded-lg transition disabled:opacity-50">
                                Crear Tarea
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </ClinicLayout>
</template>
