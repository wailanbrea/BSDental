<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import {
    AlertTriangle,
    Bell,
    CalendarDays,
    CheckCheck,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    Info,
    Package,
    UsersRound,
} from 'lucide-vue-next'
import { computed } from 'vue'

interface NotificationItem {
    id: string
    type: string
    severity: 'info' | 'success' | 'warning' | 'critical'
    title: string
    message: string
    action_url: string | null
    read_at: string | null
    created_at: string
}

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = defineProps<{
    notificationPage: {
        data: NotificationItem[]
        links: PaginationLink[]
        from: number | null
        to: number | null
        total: number
    }
    filters: { status: string; severity: string }
}>()

const unreadOnPage = computed(() => props.notificationPage.data.filter((item) => !item.read_at).length)

function applyFilters(status: string, severity: string) {
    router.get(appUrl('/notifications'), { status: status || undefined, severity: severity || undefined }, {
        preserveState: true,
        replace: true,
    })
}

function markRead(notification: NotificationItem, navigate = false) {
    router.patch(appUrl(`/notifications/${notification.id}/read`), {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (navigate && notification.action_url) router.visit(notification.action_url)
        },
    })
}

function markAllRead() {
    router.patch(appUrl('/notifications/read-all'), {}, { preserveScroll: true })
}

function iconFor(type: string) {
    if (type === 'inventory') return Package
    if (type === 'appointment') return CalendarDays
    if (type === 'follow_up') return UsersRound
    return Bell
}

function severityClasses(severity: NotificationItem['severity']) {
    if (severity === 'critical') return 'border-[#F5A3A0] bg-[#FFF1F0] text-[#B42318]'
    if (severity === 'warning') return 'border-[#FEC84B] bg-[#FFFAEB] text-[#93370D]'
    if (severity === 'success') return 'border-[#B7D9D4] bg-[#F1FAF8] text-[#006B63]'
    return 'border-[#B4C5FF] bg-[#F0F2FF] text-[#2458C6]'
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function paginationLabel(label: string) {
    return label
        .replace('&laquo; Previous', 'Anterior')
        .replace('Next &raquo;', 'Siguiente')
}
</script>

<template>
    <Head title="Centro de notificaciones — BSDental" />

    <ClinicLayout>
        <div class="mx-auto max-w-6xl space-y-5">
            <header class="flex flex-col gap-4 border-b border-[#BDC9C6] pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#007D73]">Operación clínica</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight text-[#131B2E]">Centro de notificaciones</h1>
                    <p class="mt-1 text-sm text-[#667085]">Alertas internas, tareas y eventos que requieren atención.</p>
                </div>
                <button v-if="unreadOnPage" type="button" class="inline-flex h-10 items-center justify-center gap-2 border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55] hover:bg-[#F1FAF8]" @click="markAllRead">
                    <CheckCheck class="h-4 w-4" /> Marcar todas como leídas
                </button>
            </header>

            <section class="grid gap-3 border border-[#D8E0DE] bg-white p-4 md:grid-cols-[1fr_1fr_auto]">
                <label class="field"><span>Estado</span><select :value="filters.status" @change="applyFilters(($event.target as HTMLSelectElement).value, filters.severity)"><option value="">Todas</option><option value="unread">No leídas</option><option value="read">Leídas</option></select></label>
                <label class="field"><span>Severidad</span><select :value="filters.severity" @change="applyFilters(filters.status, ($event.target as HTMLSelectElement).value)"><option value="">Todas</option><option value="critical">Crítica</option><option value="warning">Advertencia</option><option value="info">Informativa</option><option value="success">Resuelta</option></select></label>
                <div class="flex items-end"><div class="flex h-10 items-center gap-2 bg-[#F1F5F9] px-4 text-xs font-semibold text-[#52615E]"><Bell class="h-4 w-4" /> {{ notificationPage.total }} registros</div></div>
            </section>

            <section v-if="notificationPage.data.length" class="overflow-hidden border border-[#D8E0DE] bg-white">
                <article v-for="notification in notificationPage.data" :key="notification.id" class="grid gap-4 border-b border-[#D8E0DE] p-4 last:border-0 md:grid-cols-[44px_1fr_auto] md:items-center" :class="!notification.read_at ? 'bg-white' : 'bg-[#F8FAFC]'">
                    <div class="grid h-11 w-11 place-items-center border" :class="severityClasses(notification.severity)">
                        <component :is="iconFor(notification.type)" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="!notification.read_at" class="h-2 w-2 rounded-full bg-[#007D73]"></span>
                            <h2 class="text-sm font-bold text-[#131B2E]">{{ notification.title }}</h2>
                            <span class="border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide" :class="severityClasses(notification.severity)">{{ notification.severity }}</span>
                        </div>
                        <p class="mt-1 text-sm leading-5 text-[#52615E]">{{ notification.message }}</p>
                        <p class="mt-2 font-mono text-[11px] text-[#667085]">{{ formatDate(notification.created_at) }}</p>
                    </div>
                    <div class="flex items-center gap-2 md:justify-end">
                        <button v-if="!notification.read_at" type="button" class="inline-flex h-9 items-center gap-2 border border-[#9AAEAA] bg-white px-3 text-xs font-semibold text-[#344054] hover:bg-[#F1F5F9]" @click="markRead(notification)"><CheckCircle2 class="h-4 w-4" /> Marcar leída</button>
                        <button v-if="notification.action_url" type="button" class="inline-flex h-9 items-center gap-1 bg-[#005C55] px-3 text-xs font-semibold text-white hover:bg-[#004C47]" @click="markRead(notification, true)">Abrir <ChevronRight class="h-4 w-4" /></button>
                    </div>
                </article>
            </section>

            <section v-else class="border border-dashed border-[#9AAEAA] bg-white px-6 py-16 text-center">
                <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[#D8ECE9] text-[#005C55]"><CheckCheck class="h-6 w-6" /></div>
                <h2 class="mt-4 text-lg font-semibold text-[#131B2E]">Todo al día</h2>
                <p class="mt-1 text-sm text-[#667085]">No hay notificaciones que coincidan con estos filtros.</p>
            </section>

            <nav v-if="notificationPage.links.length > 3" class="flex flex-wrap items-center justify-between gap-3 text-xs text-[#667085]">
                <span>Mostrando {{ notificationPage.from }}–{{ notificationPage.to }} de {{ notificationPage.total }}</span>
                <div class="flex gap-1"><Link v-for="link in notificationPage.links" :key="link.label" :href="link.url || '#'" class="grid min-h-9 min-w-9 place-items-center border px-2" :class="link.active ? 'border-[#005C55] bg-[#005C55] text-white' : 'border-[#D8E0DE] bg-white text-[#52615E]'" :aria-disabled="!link.url"><span>{{ paginationLabel(link.label) }}</span></Link></div>
            </nav>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="flex items-start gap-3 border border-[#B4C5FF] bg-[#F0F2FF] p-3 text-xs text-[#344054]"><Info class="h-4 w-4 shrink-0 text-[#2458C6]" /><span>Las alertas informativas describen cambios y recordatorios operativos.</span></div>
                <div class="flex items-start gap-3 border border-[#FEC84B] bg-[#FFFAEB] p-3 text-xs text-[#7A2E0E]"><AlertTriangle class="h-4 w-4 shrink-0 text-[#B54708]" /><span>Las advertencias deben revisarse dentro de la jornada.</span></div>
                <div class="flex items-start gap-3 border border-[#F5A3A0] bg-[#FFF1F0] p-3 text-xs text-[#912018]"><CircleAlert class="h-4 w-4 shrink-0" /><span>Las alertas críticas requieren intervención prioritaria.</span></div>
            </div>
        </div>
    </ClinicLayout>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: 0.375rem; }
.field span { color: #52615e; font-size: 0.75rem; font-weight: 600; }
.field select { height: 2.5rem; border: 1px solid #bdc9c6; border-radius: 0.25rem; background: #fff; padding: 0 0.75rem; color: #131b2e; font-size: 0.875rem; outline: none; }
.field select:focus { border-color: #007d73; box-shadow: inset 0 0 0 1px #007d73; }
</style>
