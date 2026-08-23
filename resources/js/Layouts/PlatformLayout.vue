<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3'
import type { PageProps } from '@/types'
import { ref, computed } from 'vue'
import { 
    LayoutDashboard, 
    Building2, 
    Activity, 
    ShieldCheck, 
    LogOut, 
    Menu, 
    X,
    ChevronDown,
    CheckCircle2,
    AlertCircle
} from 'lucide-vue-next'

const page = usePage<PageProps>()
const isMobileMenuOpen = ref(false)
const isProfileDropdownOpen = ref(false)

const user = computed(() => page.props.auth?.user || { name: 'Admin Landlord', email: 'admin@bsdental.io' })
const flash = computed(() => page.props.flash || {})
const currentUrl = computed(() => page.url)

const navItems = [
    { name: 'Dashboard Central', href: '/platform/dashboard', icon: LayoutDashboard, pattern: /^\/platform\/dashboard/ },
    { name: 'Clínicas / Tenants', href: '/platform/tenants', icon: Building2, pattern: /^\/platform\/tenants/ },
    { name: 'Operaciones & BD', href: '/platform/operations', icon: Activity, pattern: /^\/platform\/operations/ },
]

function isActive(item: typeof navItems[0]) {
    return item.pattern.test(currentUrl.value)
}

function logout() {
    router.post('/platform/logout')
}
</script>

<template>
    <div class="min-h-screen bg-[#F8FAFC] text-[#131B2E] font-sans antialiased flex flex-col selection:bg-[#005C55] selection:text-white">
        <!-- SideNavBar Desktop (260px) -->
        <nav class="w-[260px] h-screen fixed left-0 top-0 hidden md:flex flex-col bg-white border-r border-[#E2E8F0] z-50 py-4">
            <!-- Brand Logo -->
            <div class="px-5 mb-8 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#131B2E] text-white flex items-center justify-center shadow-xs">
                    <ShieldCheck class="w-5 h-5 text-[#80D5CB]" />
                </div>
                <div>
                    <h1 class="text-lg font-bold text-[#131B2E] tracking-tight leading-none">BSDental Core</h1>
                    <p class="text-[11px] font-semibold text-[#005C55] uppercase tracking-wider mt-1">Platform Admin</p>
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

            <!-- Logout / Bottom Action -->
            <div class="px-3 pt-3 border-t border-[#E2E8F0] mt-auto">
                <button 
                    class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg text-sm font-medium text-[#BA1A1A] hover:bg-[#FFDAD6]/30 transition-colors text-left"
                    @click="logout"
                >
                    <LogOut class="w-4 h-4 shrink-0" />
                    <span>Cerrar Sesión</span>
                </button>
            </div>
        </nav>

        <!-- Main Wrapper -->
        <div class="flex-1 ml-0 md:ml-[260px] flex flex-col min-h-screen">
            <!-- TopNavBar Sticky (64px) -->
            <header class="h-16 bg-white border-b border-[#E2E8F0] sticky top-0 z-40 px-4 sm:px-6 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-4 flex-1">
                    <button 
                        class="md:hidden p-2 text-[#505F76] hover:bg-[#F1F5F9] rounded-lg transition"
                        @click="isMobileMenuOpen = !isMobileMenuOpen"
                    >
                        <Menu v-if="!isMobileMenuOpen" class="w-5 h-5" />
                        <X v-else class="w-5 h-5" />
                    </button>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#F2F3FF] text-[#005C55] border border-[#BDC9C6]">
                            Landlord Central Domain
                        </span>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <button 
                            class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-[#F1F5F9] transition"
                            @click="isProfileDropdownOpen = !isProfileDropdownOpen"
                        >
                            <div class="w-8 h-8 rounded-full bg-[#131B2E] text-white flex items-center justify-center font-bold text-xs shadow-xs">
                                SA
                            </div>
                            <div class="hidden sm:block text-left">
                                <div class="text-xs font-bold text-[#131B2E] leading-tight">{{ user.name }}</div>
                                <div class="text-[10px] text-[#505F76] leading-tight">{{ user.email }}</div>
                            </div>
                            <ChevronDown class="w-3.5 h-3.5 text-[#505F76] hidden sm:block" />
                        </button>

                        <div 
                            v-if="isProfileDropdownOpen"
                            class="absolute right-0 mt-2 w-48 bg-white border border-[#E2E8F0] rounded-xl shadow-lg py-2 z-50"
                            @click="isProfileDropdownOpen = false"
                        >
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

            <!-- Main Content Slot -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
