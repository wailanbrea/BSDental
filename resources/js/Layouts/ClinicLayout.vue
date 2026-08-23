<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'
import { ref, computed } from 'vue'
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
    AlertCircle
} from 'lucide-vue-next'

type ClinicPageProps = PageProps<{
    clinic?: { trade_name?: string | null; name?: string | null; clinic_name?: string | null }
}>

const page = usePage<ClinicPageProps>()
const isMobileMenuOpen = ref(false)
const isProfileDropdownOpen = ref(false)

const user = computed(() => page.props.auth?.user || { name: 'Usuario', email: 'usuario@bsdental.com' })
const clinic = computed(() => page.props.clinic || { trade_name: 'BSDental', name: 'BSDental Clinic', clinic_name: 'BSDental Clinic' })
const flash = computed(() => page.props.flash || {})

const currentUrl = computed(() => page.url)

const navItems = [
    { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, pattern: /^\/dashboard/ },
    { name: 'Pacientes', href: '/patients', icon: Users, pattern: /^\/patients/ },
    { name: 'Agenda', href: '/appointments', icon: Calendar, pattern: /^\/appointments/ },
    { name: 'Clínica', href: '/encounters', icon: Stethoscope, pattern: /^\/(encounters|odontogram)/ },
    { name: 'Administración', href: '/cash-registers', icon: Briefcase, pattern: /^\/(cash-registers|billing|branches|professionals|inventory|lab)/ },
    { name: 'CRM', href: '/crm', icon: Megaphone, pattern: /^\/crm/ },
    { name: 'Analítica', href: '/analytics', icon: BarChart3, pattern: /^\/analytics/ },
]

function isActive(item: typeof navItems[0]) {
    return item.pattern.test(currentUrl.value)
}

const searchQuery = ref('')
function onSearchSubmit() {
    if (searchQuery.value.trim().length > 0) {
        router.get('/patients', { search: searchQuery.value.trim() })
    }
}

function logout() {
    router.post('/logout')
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
                        :href="item.href"
                        :class="[
                            'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-all duration-150',
                            isActive(item) 
                                ? 'bg-[#F2F3FF] text-[#005C55] font-bold border-r-4 border-[#005C55] shadow-xs' 
                                : 'text-[#505F76] hover:bg-[#F1F5F9] hover:text-[#131B2E]'
                        ]"
                    >
                        <component :is="item.icon" class="w-4 h-4 shrink-0" />
                        <span>{{ item.name }}</span>
                    </Link>
                </li>
            </ul>

            <!-- Settings & Bottom Action -->
            <div class="px-3 pt-3 border-t border-[#E2E8F0] mt-auto">
                <Link 
                    href="/settings"
                    :class="[
                        'flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition-colors',
                        currentUrl.startsWith('/settings') 
                            ? 'bg-[#F2F3FF] text-[#005C55] font-bold border-r-4 border-[#005C55]' 
                            : 'text-[#505F76] hover:bg-[#F1F5F9] hover:text-[#131B2E]'
                    ]"
                >
                    <Settings class="w-4 h-4 shrink-0" />
                    <span>Configuración</span>
                </Link>
            </div>
        </nav>

        <!-- Main Wrapper -->
        <div class="flex-1 ml-0 md:ml-[260px] flex flex-col min-h-screen">
            <!-- TopNavBar Sticky (64px) -->
            <header class="h-16 bg-white border-b border-[#E2E8F0] sticky top-0 z-40 px-4 sm:px-6 flex items-center justify-between shadow-xs">
                <!-- Mobile Menu Button & Search -->
                <div class="flex items-center gap-4 flex-1">
                    <button 
                        class="md:hidden p-2 text-[#505F76] hover:bg-[#F1F5F9] rounded-lg transition"
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                    >
                        <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                        <X v-else class="w-5 h-5" />
                    </button>

                    <!-- Global Patient Search Bar -->
                    <form @submit.prevent="onSearchSubmit" class="relative hidden sm:flex items-center w-full max-w-md">
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
                        href="/inventory" 
                        class="p-2 text-[#505F76] hover:text-[#005C55] hover:bg-[#F1F5F9] rounded-full transition"
                        title="Inventario & Almacén"
                    >
                        <Package class="w-4 h-4" />
                    </Link>

                    <!-- Notifications -->
                    <button 
                        class="p-2 text-[#505F76] hover:text-[#005C55] hover:bg-[#F1F5F9] rounded-full transition relative"
                        title="Notificaciones"
                    >
                        <Bell class="w-4 h-4" />
                        <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-[#BA1A1A] rounded-full"></span>
                    </button>

                    <!-- User Profile Dropdown -->
                    <div class="relative ml-2">
                        <button 
                            @click="isProfileDropdownOpen = !isProfileDropdownOpen"
                            class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-[#F1F5F9] transition"
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
                            @click="isProfileDropdownOpen = false"
                            class="absolute right-0 mt-2 w-52 bg-white border border-[#E2E8F0] rounded-xl shadow-lg py-2 z-50"
                        >
                            <div class="px-4 py-2 border-b border-[#E2E8F0]">
                                <p class="text-xs font-bold text-[#131B2E]">{{ user.name }}</p>
                                <p class="text-[10px] text-[#505F76] truncate">{{ user.email }}</p>
                            </div>
                            <Link href="/settings" class="flex items-center gap-2 px-4 py-2 text-xs text-[#505F76] hover:bg-[#F8FAFC] hover:text-[#131B2E]">
                                <Settings class="w-3.5 h-3.5" /> Configuración de Clínica
                            </Link>
                            <button 
                                @click="logout" 
                                class="w-full flex items-center gap-2 px-4 py-2 text-xs text-[#BA1A1A] hover:bg-[#FFDAD6]/30 text-left"
                            >
                                <LogOut class="w-3.5 h-3.5" /> Cerrar Sesión
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Drawer Navigation -->
            <div v-if="isMobileMenuOpen" class="md:hidden bg-white border-b border-[#E2E8F0] px-4 py-3 space-y-1">
                <Link 
                    v-for="item in navItems" 
                    :key="item.href" 
                    :href="item.href"
                    @click="isMobileMenuOpen = false"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium',
                        isActive(item) ? 'bg-[#F2F3FF] text-[#005C55] font-bold' : 'text-[#505F76]'
                    ]"
                >
                    <component :is="item.icon" class="w-4 h-4" />
                    <span>{{ item.name }}</span>
                </Link>
                <button 
                    @click="logout" 
                    class="w-full flex items-center gap-3 px-3 py-2 text-sm text-[#BA1A1A] text-left"
                >
                    <LogOut class="w-4 h-4" /> Cerrar Sesión
                </button>
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
