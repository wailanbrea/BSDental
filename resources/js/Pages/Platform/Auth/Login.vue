<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { 
    ShieldCheck, 
    Mail, 
    Lock, 
    Eye, 
    EyeOff, 
    ArrowRight, 
    AlertCircle 
} from 'lucide-vue-next'

interface Props {
    status?: string | null
}

defineProps<Props>()

const showPassword = ref(false)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/platform/login', {
        onFinish: () => {
            form.reset('password')
        },
    })
}
</script>

<template>
    <Head title="BSDental Core — Platform Admin" />

    <div class="bg-[#F2F3FF] min-h-screen flex items-center justify-center p-4 sm:p-6 font-sans antialiased selection:bg-[#005C55] selection:text-white">
        <div class="w-full max-w-md bg-white border border-[#BDC9C6] rounded-2xl shadow-[0px_4px_12px_rgba(15,23,42,0.08)] overflow-hidden">
            <!-- Header Section -->
            <div class="p-8 flex flex-col items-center border-b border-[#E2E8F0] bg-[#FAF8FF]">
                <div class="w-16 h-16 bg-[#131B2E] text-white rounded-full flex items-center justify-center mb-4 shadow-sm">
                    <ShieldCheck class="w-8 h-8 text-[#80D5CB]" />
                </div>
                <h1 class="text-2xl font-bold text-[#131B2E] tracking-tight">BSDental Core</h1>
                <p class="text-sm font-medium text-[#505F76] mt-1 text-center">
                    Centro de Operaciones y Control Platform
                </p>
            </div>

            <!-- Form Section -->
            <div class="p-8">
                <div v-if="status" class="mb-5 text-xs font-medium text-emerald-800 bg-emerald-50 p-3 rounded-lg border border-emerald-200">
                    {{ status }}
                </div>

                <div v-if="form.errors.email" class="mb-5 text-xs font-medium text-rose-800 bg-rose-50 p-3 rounded-lg border border-rose-200 flex items-center gap-2">
                    <AlertCircle class="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{{ form.errors.email }}</span>
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Email field -->
                    <div class="flex flex-col space-y-1.5">
                        <label class="font-label-caps text-[#3E4947]" for="email">Correo de Plataforma</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#505F76]">
                                <Mail class="w-4 h-4" />
                            </div>
                            <input 
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="admin@bsdental.io"
                                class="w-full pl-10 pr-3.5 py-2.5 bg-white border border-[#BDC9C6] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#005C55] focus:border-[#005C55] text-sm text-[#131B2E] transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Password field -->
                    <div class="flex flex-col space-y-1.5">
                        <label class="font-label-caps text-[#3E4947]" for="password">Contraseña Maestra</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#505F76]">
                                <Lock class="w-4 h-4" />
                            </div>
                            <input 
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••••••"
                                class="w-full pl-10 pr-10 py-2.5 bg-white border border-[#BDC9C6] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#005C55] focus:border-[#005C55] text-sm text-[#131B2E] transition-colors font-mono"
                            />
                            <button 
                                type="button"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#505F76] hover:text-[#005C55] transition-colors focus:outline-none"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="w-4 h-4" />
                                <Eye v-else class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Remember session -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center cursor-pointer select-none">
                            <input 
                                id="remember"
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 text-[#005C55] bg-white border-[#BDC9C6] rounded focus:ring-[#005C55]"
                            />
                            <span class="ml-2 text-xs font-medium text-[#505F76]">Recordar sesión</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-[#131B2E] text-white font-semibold text-sm py-3 rounded-lg hover:bg-[#005C55] active:bg-[#00504A] transition-colors flex justify-center items-center gap-2 shadow-[0px_2px_4px_rgba(0,0,0,0.1)] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#005C55] disabled:opacity-50"
                        >
                            <span>{{ form.processing ? 'Verificando...' : 'Acceder al Centro de Control' }}</span>
                            <ArrowRight class="w-4 h-4 text-[#80D5CB]" />
                        </button>
                    </div>
                </form>
            </div>

            <!-- Footer Section -->
            <div class="p-4 bg-[#FAF8FF] text-center border-t border-[#E2E8F0]">
                <p class="text-xs font-medium text-[#505F76]">
                    Acceso restringido a personal de Operaciones y Plataforma
                </p>
            </div>
        </div>
    </div>
</template>
