<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import type { PageProps } from '@/types'
import { ref, computed, watch } from 'vue'
import { 
    LayoutDashboard, 
    Users, 
    Calendar, 
    Stethoscope, 
    Briefcase, 
    Megaphone, 
    BarChart3, 
    Settings, 
    Search, 
    Bell, 
    Package, 
    LogOut, 
    Menu, 
    X,
    ChevronDown,
    Building2,
    CheckCircle2,
    AlertCircle,
    ShieldCheck,
    ReceiptText,
    FlaskConical,
    Landmark,
    Wallet,
} from 'lucide-vue-next'

interface LayoutNotification {
    id: string
    type: string
    severity: 'info' | 'success' | 'warning' | 'critical'
    title: string
    message: string
    action_url: string | null
    read_at: string | null
    created_at: string
}

type ClinicPageProps = PageProps<{
    clinic?: { trade_name?: string | null; name?: string | null; clinic_name?: string | null }
    notifications?: {
        items: LayoutNotification[]
        unread_count: number
    }
}>

const page = usePage<ClinicPageProps>()
const isMobileMenuOpen = ref(false)
const isProfileDropdownOpen = ref(false)
const isNotificationsOpen = ref(false)

function closeMobileMenu() {
    isMobileMenuOpen.value = false
}

function toggleMobileMenu() {
    isMobileMenuOpen.value = !isMobileMenuOpen.value
}

watch(isMobileMenuOpen, (isOpen) => {
    document.body.classList.toggle('overflow-hidden', isOpen)
})


const user = computed(() => page.props.auth?.user || { id: '', name: 'Usuario', email: 'usuario@bsdental.com', permissions: [], roles: [], branch_ids: [] })
const clinic = computed(() => page.props.clinic || { trade_name: 'BSDental', name: 'BSDental Clinic', clinic_name: 'BSDental Clinic' })
const flash = computed(() => page.props.flash || {})
const notificationCenter = computed(() => page.props.notifications || { items: [], unread_count: 0 })
const permissions = computed(() => new Set(user.value.permissions || []))
const isOwner = computed(() => (user.value.roles || []).includes('Owner'))

function canAny(required: string[] = []) {
    return required.length === 0 || isOwner.value || required.some((permission) => permissions.value.has(permission))
}

const currentUrl = computed(() => page.url)
const appBasePath = (import.meta.env.VITE_BASE_PATH || '').replace(/\/build\/?$/, '')
const currentPath = computed(() => {
    const path = currentUrl.value.split('?')[0]

    if (appBasePath && (path === appBasePath || path.startsWith(`${appBasePath}/`))) {
        return path.slice(appBasePath.length) || '/'
    }

    const publicSegment = '/public/'
    const publicIndex = path.indexOf(publicSegment)

    return publicIndex >= 0 ? path.slice(publicIndex + publicSegment.length - 1) : path
})


const navItems = computed(() => [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, pattern: /^\/dashboard/ },
    { name: 'Pacientes', href: '/patients', icon: Users, pattern: /^\/patients/, permissions: ['patients.view'] },
    { name: 'Agenda', href: '/appointments', icon: Calendar, pattern: /^\/appointments/, permissions: ['appointments.view'] },
    { name: 'Clínica', href: '/encounters', icon: Stethoscope, pattern: /^\/(encounters|odontogram)/, permissions: ['clinical.view', 'odontogram.view'] },
    { name: 'Presupuestos', href: '/quotes', icon: Briefcase, pattern: /^\/(quotes|treatment-plans)/, permissions: ['quotes.view'] },
    { name: 'Facturación', href: '/cash-registers', icon: ReceiptText, pattern: /^\/(cash-registers|charges|payments)/, permissions: ['cash.view', 'payments.view', 'finance.view'] },
    { name: 'Cuentas por cobrar', href: '/billing/aging-receivables', icon: Wallet, pattern: /^\/billing\/aging-receivables/, permissions: ['finance.reports'] },
    { name: 'Nómina', href: '/payroll', icon: Landmark, pattern: /^\/payroll/, permissions: ['finance.reports'] },
    { name: 'Inventario', href: '/inventory', icon: Package, pattern: /^\/inventory/, permissions: ['inventory.view'] },
    { name: 'Laboratorio', href: '/lab', icon: FlaskConical, pattern: /^\/lab/, permissions: ['lab.view'] },
    { name: 'CRM', href: '/crm', icon: Megaphone, pattern: /^\/crm/, permissions: ['crm.view'] },
    { name: 'Analítica', href: '/analytics', icon: BarChart3, pattern: /^\/analytics/, permissions: ['finance.reports'] },
    { name: 'Usuarios', href: '/users', icon: ShieldCheck, pattern: /^\/users/, permissions: ['users.view'] },
].filter((item) => canAny(item.permissions)))

function isActive(item: { pattern: RegExp }) {
    return item.pattern.test(currentPath.value)
}

function isSettingsActive() {
    return currentPath.value.startsWith('/settings')
}

const searchQuery = ref('')
function onSearchSubmit() {
    if (searchQuery.value.trim().length > 0) {
        router.get(appUrl('/patients'), { search: searchQuery.value.trim() })
    }
}

function logout() {
    router.post(appUrl('/logout'))
}

function toggleNotifications() {
    isNotificationsOpen.value = !isNotificationsOpen.value
    isProfileDropdownOpen.value = false
}

function openNotification(notification: LayoutNotification) {
    isNotificationsOpen.value = false

    if (notification.read_at) {
        if (notification.action_url) router.visit(notification.action_url)
        return
    }

    router.patch(appUrl(`/notifications/${notification.id}/read`), {}, {
        preserveScroll: true,
        only: ['notifications'],
        onSuccess: () => {
            if (notification.action_url) router.visit(notification.action_url)
        },
    })
}

function markAllNotificationsRead() {
    router.patch(appUrl('/notifications/read-all'), {}, {
        preserveScroll: true,
        only: ['notifications'],
    })
}

function formatNotificationDate(value: string) {
    return new Intl.DateTimeFormat('es-DO', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

function notificationTone(severity: 'info' | 'success' | 'warning' | 'critical') {
    if (severity === 'critical') return 'bg-[#FFF1F0] text-[#B42318] border-[#F5A3A0]'
    if (severity === 'warning') return 'bg-[#FFFAEB] text-[#93370D] border-[#FEC84B]'
    if (severity === 'success') return 'bg-[#F1FAF8] text-[#006B63] border-[#B7D9D4]'
    return 'bg-[#F0F2FF] text-[#2458C6] border-[#B4C5FF]'
}
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] text-[#131B2E] font-sans antialiased flex flex-col selection:bg-[#005C55] selection:text-white">
        <!-- SideNavBar Desktop (260px) -->
        <nav class="w-[260px] h-screen fixed left-0 top-0 hidden md:flex flex-col bg-white border-r border-[#E2E8F0] z-50 py-4">
            <!-- Brand Logo -->
            <div class="px-5 mb-8 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#005C55] text-white flex items-center justify-center shadow-xs">
                    <Stethoscope class="w-5 h-5" />
                </div>
                <div>
                    <h1 class="text-lg font-bold text-[#005C55] tracking-tight leading-none">BSDental v4</h1>
                    <p class="text-[11px] font-semibold text-[#505F76] uppercase tracking-wider mt-1">Clinical Platform</p>
                </div>
            </div>

            <!-- Navigation Links -->
            <ul class="flex-1 px-3 flex flex-col gap-1 overflow-y-auto">
                <li v-for="item in navItems" :key="item.href">
                    <Link 
                        :href="appUrl(item.href)"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        :class="[
                            'relative flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all duration-150',
                            isActive(item) 
                                ? 'bg-[#E6F4F1] text-[#005C55] font-bold shadow-xs'
                                : 'text-[#505F76] hover:bg-[#F1F5F9] hover:text-[#131B2E]'
                        ]"
                    >
                        <span v-if="isActive(item)" aria-hidden="true" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-[#005C55]" />
                        <component :is="item.icon" class="w-4 h-4 shrink-0" />
                        <span>{{ item.name }}</span>
                    </Link>
                </li>
            </ul>

            <!-- Settings & Bottom Action -->
            <div class="px-3 pt-3 border-t border-[#E2E8F0] mt-auto">
                <Link
                    v-if="canAny(['settings.view'])"
                    :aria-current="isSettingsActive() ? 'page' : undefined"
                    :href="appUrl('/settings')"
                    :class="[
                        'relative flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        isSettingsActive()
                            ? 'bg-[#E6F4F1] text-[#005C55] font-bold shadow-xs'
                            : 'text-[#505F76] hover:bg-[#F1F5F9] hover:text-[#131B2E]'
                    ]"
                >
                    <span v-if="isSettingsActive()" aria-hidden="true" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-[#005C55]" />
                    <Settings class="w-4 h-4 shrink-0" />
                    <span>Configuración</span>
                </Link>
            </div>
        </nav>

        <!-- Main Wrapper -->
        <div class="flex-1 ml-0 md:ml-[260px] flex flex-col min-h-screen">
            <!-- TopNavBar Sticky (64px) -->
            <header :class="isNotificationsOpen ? 'z-[60]' : 'z-40'" class="h-16 bg-white border-b border-[#E2E8F0] sticky top-0 px-4 sm:px-6 flex items-center justify-between shadow-xs">
                <!-- Mobile Menu Button & Search -->
                <div class="flex items-center gap-4 flex-1">
                    <button 
                        class="md:hidden p-2 text-[#505F76] hover:bg-[#F1F5F9] rounded-lg transition"
                        aria-label="Toggle navigation menu"
                        :aria-expanded="isMobileMenuOpen"
                        aria-controls="mobile-navigation"
                        @click="toggleMobileMenu"
                    >
                        <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                        <X v-else class="w-5 h-5" />
                    </button>

                    <!-- Global Patient Search Bar -->
                    <form 
                        v-if="canAny(['patients.view'])"
                        class="relative hidden sm:flex items-center w-full max-w-md" 
                        @submit.prevent="onSearchSubmit"
                    >
                        <Search class="w-4 h-4 absolute left-3.5 text-[#505F76] pointer-events-none" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Buscar paciente por nombre, cédula o HC..." 
                            class="w-full bg-[#F8FAFC] border border-[#E2E8F0] rounded-full py-1.5 pl-10 pr-4 text-xs text-[#131B2E] placeholder-[#64748B] focus:outline-none focus:border-[#005C55] focus:ring-1 focus:ring-[#005C55] transition"
                        />
                    </form>
                </div>

                <!-- Right Toolbar Actions & Profile -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Branch Badge / Selector -->
                    <div class="hidden lg:flex items-center gap-2 px-3 py-1.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg text-xs text-[#505F76]">
                        <Building2 class="w-3.5 h-3.5 text-[#005C55]" />
                        <span class="font-medium text-[#131B2E]">{{ clinic.trade_name || clinic.name || clinic.clinic_name || 'Sede Principal' }}</span>
                    </div>

                    <!-- Inventory / Lab Quick Link -->
                    <Link 
                        v-if="canAny(['inventory.view'])"
                        :href="appUrl('/inventory')" 
                        class="p-2 text-[#505F76] hover:text-[#005C55] hover:bg-[#F1F5F9] rounded-full transition"
                        title="Inventario & Almacén"
                    >
                        <Package class="w-4 h-4" />
                    </Link>

                    <!-- Notifications -->
                    <div class="relative">
                        <button
                            class="relative rounded-full p-2 text-[#505F76] transition hover:bg-[#F1F5F9] hover:text-[#005C55]"
                            title="Notificaciones"
                            aria-label="Abrir notificaciones"
                            :aria-expanded="isNotificationsOpen"
                            @click="toggleNotifications"
                        >
                            <Bell class="h-4 w-4" />
                            <span v-if="notificationCenter.unread_count" class="absolute -right-1 -top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-[#BA1A1A] px-1 text-[9px] font-bold text-white">{{ Math.min(notificationCenter.unread_count, 99) }}</span>
                        </button>

                        <div v-if="isNotificationsOpen" class="fixed inset-x-4 top-16 z-50 max-h-[calc(100dvh-5rem)] overflow-hidden border border-[#D8E0DE] bg-white shadow-[0_12px_28px_rgba(15,23,42,0.16)] sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-2 sm:max-h-none sm:w-[min(380px,calc(100vw-2rem))] sm:shadow-[0_4px_12px_rgba(15,23,42,0.08)]">
                            <div class="flex items-center justify-between border-b border-[#D8E0DE] bg-[#F1F5F9] px-4 py-3">
                                <div><p class="text-sm font-bold text-[#131B2E]">Notificaciones</p><p class="text-[11px] text-[#667085]">{{ notificationCenter.unread_count }} pendientes</p></div>
                                <button v-if="notificationCenter.unread_count" type="button" class="text-[11px] font-bold text-[#006B63] hover:underline" @click="markAllNotificationsRead">Marcar todas</button>
                            </div>

                            <div v-if="notificationCenter.items.length" class="max-h-[calc(100dvh-11rem)] overflow-y-auto sm:max-h-[420px]">
                                <button v-for="notification in notificationCenter.items" :key="notification.id" type="button" class="grid w-full grid-cols-[32px_1fr] gap-3 border-b border-[#E2E8F0] p-3 text-left last:border-0 hover:bg-[#F8FAFC]" :class="notification.read_at ? 'opacity-70' : 'bg-white'" @click="openNotification(notification)">
                                    <span class="grid h-8 w-8 place-items-center border" :class="notificationTone(notification.severity)"><AlertCircle v-if="notification.severity === 'critical' || notification.severity === 'warning'" class="h-4 w-4" /><CheckCircle2 v-else-if="notification.severity === 'success'" class="h-4 w-4" /><Bell v-else class="h-4 w-4" /></span>
                                    <span class="min-w-0"><span class="flex items-start gap-2"><strong class="flex-1 text-xs text-[#131B2E]">{{ notification.title }}</strong><span v-if="!notification.read_at" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[#007D73]"></span></span><span class="mt-1 line-clamp-2 block text-[11px] leading-4 text-[#52615E]">{{ notification.message }}</span><span class="mt-1 block font-mono text-[10px] text-[#667085]">{{ formatNotificationDate(notification.created_at) }}</span></span>
                                </button>
                            </div>
                            <div v-else class="px-5 py-10 text-center"><CheckCircle2 class="mx-auto h-7 w-7 text-[#007D73]" /><p class="mt-2 text-sm font-semibold text-[#131B2E]">Todo al día</p><p class="mt-1 text-xs text-[#667085]">No tienes notificaciones pendientes.</p></div>

                            <Link :href="appUrl('/notifications')" class="flex h-10 items-center justify-center border-t border-[#D8E0DE] text-xs font-bold text-[#006B63] hover:bg-[#F1FAF8]" @click="isNotificationsOpen = false">Ver centro de notificaciones</Link>
                        </div>
                    </div>

                    <!-- User Profile Dropdown -->
                    <div class="relative ml-2">
                        <button 
                            class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-[#F1F5F9] transition"
                            @click="isProfileDropdownOpen = !isProfileDropdownOpen; isNotificationsOpen = false"
                        >
                            <div class="w-8 h-8 rounded-full bg-[#005C55] text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                {{ user.name?.charAt(0) || 'U' }}
                            </div>
                            <div class="hidden sm:block text-left">
                                <div class="text-xs font-bold text-[#131B2E] leading-tight">{{ user.name }}</div>
                                <div class="text-[10px] text-[#505F76] leading-tight">{{ user.email }}</div>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-[#505F76] hidden sm:block" />
                        </button>

                        <!-- Dropdown Menu -->
                        <div 
                            v-if="isProfileDropdownOpen"
                            class="absolute right-0 mt-2 w-52 bg-white border border-[#E2E8F0] rounded-xl shadow-lg py-2 z-50"
                            @click="isProfileDropdownOpen = false"
                        >
                            <div class="px-4 py-2 border-b border-[#E2E8F0]">
                                <p class="text-xs font-bold text-[#131B2E]">{{ user.name }}</p>
                                <p class="text-[10px] text-[#505F76] truncate">{{ user.email }}</p>
                            </div>
                            <Link :href="appUrl('/settings')" class="flex items-center gap-2 px-4 py-2 text-xs text-[#505F76] hover:bg-[#F8FAFC] hover:text-[#131B2E]">
                                <Settings class="w-3.5 h-3.5" /> Configuración de Clínica
                            </Link>
                            <button 
                                class="w-full flex items-center gap-2 px-4 py-2 text-xs text-[#BA1A1A] hover:bg-[#FFDAD6]/30 text-left"
                                @click="logout"
                            >
                                <LogOut class="w-3.5 h-3.5" /> Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile drawer overlays the workspace instead of pushing it down. -->
            <div v-if="isMobileMenuOpen" id="mobile-navigation" class="fixed inset-x-0 bottom-0 top-16 z-50 md:hidden">
                <button type="button" class="absolute inset-0 bg-[#0F172A]/35" aria-label="Cerrar menú" @click="closeMobileMenu" />
                <nav class="absolute inset-y-0 left-0 flex w-[min(320px,calc(100%-3rem))] flex-col overflow-y-auto bg-white px-4 py-4 shadow-[8px_0_24px_rgba(15,23,42,0.18)]">
                    <div class="space-y-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            :href="appUrl(item.href)"
                            :class="[
                                'relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium',
                                isActive(item) ? 'bg-[#E6F4F1] text-[#005C55] font-bold shadow-xs' : 'text-[#505F76] hover:bg-[#F1F5F9]'
                            ]"
                            @click="closeMobileMenu"
                        >
                            <span v-if="isActive(item)" aria-hidden="true" class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-[#005C55]" />
                            <component :is="item.icon" class="w-4 h-4" />
                            <span>{{ item.name }}</span>
                        </Link>
                    </div>
                    <button
                        class="mt-auto flex w-full items-center gap-3 border-t border-[#E2E8F0] px-3 py-4 text-left text-sm text-[#BA1A1A]"
                        @click="closeMobileMenu(); logout()"
                    >
                        <LogOut class="w-4 h-4" /> Cerrar Sesión
                    </button>
                </nav>
            </div>

            <!-- Global Toast Messages -->
            <div v-if="flash?.success || flash?.error" class="px-6 pt-4">
                <div 
                    v-if="flash?.success" 
                    class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs flex items-center gap-2 shadow-xs"
                >
                    <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{{ flash.success }}</span>
                </div>
                <div 
                    v-if="flash?.error" 
                    class="p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-xs flex items-center gap-2 shadow-xs"
                >
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{{ flash.error }}</span>
                </div>
            </div>

            <!-- Main Page Slot Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
