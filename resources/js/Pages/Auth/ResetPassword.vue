<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ArrowLeft, Check, Eye, EyeOff, KeyRound, Lock, Mail, Stethoscope } from 'lucide-vue-next'
import { ref } from 'vue'

interface Props {
    token: string
    email: string
    clinic?: { name: string; trade_name: string }
}

const props = defineProps<Props>()
const showPassword = ref(false)

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => form.post(appUrl('/reset-password'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
})
</script>

<template>
    <Head :title="`${clinic?.trade_name ?? 'BSDental'} — Nueva contraseña`" />

    <main class="min-h-screen bg-[#F2F3FF] px-4 py-8 font-sans antialiased sm:px-6">
        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-[#BDC9C6] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.08)]">
                <header class="border-b border-[#E2E8F0] bg-[#FAF8FF] p-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#0F766E] text-white shadow-sm">
                        <Stethoscope class="h-8 w-8 text-[#A3FAEF]" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#0F766E]">Nueva credencial</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#131B2E]">Crea una nueva contraseña</h1>
                    <p class="mt-2 text-sm leading-6 text-[#505F76]">Elige una clave robusta para proteger el acceso clínico.</p>
                </header>

                <div class="p-8">
                    <div v-if="form.errors.email || form.errors.token" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
                        {{ form.errors.email ?? form.errors.token }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="email" class="font-label-caps text-[#3E4947]">Correo electrónico</label>
                            <div class="relative mt-1.5">
                                <Mail class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#505F76]" />
                                <input id="email" v-model="form.email" type="email" required autocomplete="email" class="w-full rounded-lg border border-[#BDC9C6] py-2.5 pl-10 pr-3.5 text-sm text-[#131B2E] outline-none focus:border-[#005C55] focus:ring-2 focus:ring-[#005C55]" />
                            </div>
                        </div>

                        <div>
                            <label for="password" class="font-label-caps text-[#3E4947]">Nueva contraseña</label>
                            <div class="relative mt-1.5">
                                <Lock class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#505F76]" />
                                <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" class="w-full rounded-lg border border-[#BDC9C6] py-2.5 pl-10 pr-10 text-sm text-[#131B2E] outline-none focus:border-[#005C55] focus:ring-2 focus:ring-[#005C55]" />
                                <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-[#505F76] hover:text-[#005C55]" aria-label="Mostrar u ocultar contraseña" @click="showPassword = !showPassword">
                                    <EyeOff v-if="showPassword" class="h-4 w-4" />
                                    <Eye v-else class="h-4 w-4" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1.5 text-xs font-medium text-rose-700">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="font-label-caps text-[#3E4947]">Confirmar contraseña</label>
                            <div class="relative mt-1.5">
                                <KeyRound class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#505F76]" />
                                <input id="password_confirmation" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" required autocomplete="new-password" class="w-full rounded-lg border border-[#BDC9C6] py-2.5 pl-10 pr-3.5 text-sm text-[#131B2E] outline-none focus:border-[#005C55] focus:ring-2 focus:ring-[#005C55]" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 rounded-xl border border-[#D7E2DF] bg-[#F7FAF9] p-4 text-xs text-[#505F76]">
                            <span class="flex items-center gap-1.5"><Check class="h-3.5 w-3.5 text-[#0F766E]" />12 caracteres</span>
                            <span class="flex items-center gap-1.5"><Check class="h-3.5 w-3.5 text-[#0F766E]" />Mayúsculas y minúsculas</span>
                            <span class="flex items-center gap-1.5"><Check class="h-3.5 w-3.5 text-[#0F766E]" />Al menos un número</span>
                            <span class="flex items-center gap-1.5"><Check class="h-3.5 w-3.5 text-[#0F766E]" />Al menos un símbolo</span>
                        </div>

                        <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#005C55] py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00504A] focus:outline-none focus:ring-2 focus:ring-[#005C55] focus:ring-offset-2 disabled:opacity-50">
                            {{ form.processing ? 'Actualizando…' : 'Guardar nueva contraseña' }}
                            <Check class="h-4 w-4" />
                        </button>
                    </form>

                    <Link :href="appUrl('/login')" class="mt-6 flex items-center justify-center gap-2 text-sm font-semibold text-[#005C55] hover:underline">
                        <ArrowLeft class="h-4 w-4" />
                        Volver al inicio de sesión
                    </Link>
                </div>
            </section>
        </div>
    </main>
</template>
