<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import { ArrowLeft, ArrowRight, CheckCircle2, Mail, ShieldCheck, Stethoscope } from 'lucide-vue-next'

interface Props {
    status?: string | null
    clinic?: { name: string; trade_name: string }
}

defineProps<Props>()

const form = useForm({ email: '' })

const submit = () => form.post(appUrl('/forgot-password'))
</script>

<template>
    <Head :title="`${clinic?.trade_name ?? 'BSDental'} — Recuperar acceso`" />

    <main class="min-h-screen bg-[#F2F3FF] px-4 py-8 font-sans antialiased sm:px-6">
        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md items-center">
            <section class="w-full overflow-hidden rounded-2xl border border-[#BDC9C6] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.08)]">
                <header class="border-b border-[#E2E8F0] bg-[#FAF8FF] p-8 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#0F766E] text-white shadow-sm">
                        <Stethoscope class="h-8 w-8 text-[#A3FAEF]" />
                    </div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#0F766E]">Acceso seguro</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#131B2E]">Recupera tu contraseña</h1>
                    <p class="mt-2 text-sm leading-6 text-[#505F76]">
                        Enviaremos un enlace de un solo uso al correo registrado en {{ clinic?.trade_name ?? 'la clínica' }}.
                    </p>
                </header>

                <div class="p-8">
                    <div v-if="status" class="mb-6 flex gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                        <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                        <span>{{ status }}</span>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label for="email" class="font-label-caps text-[#3E4947]">Correo electrónico</label>
                            <div class="relative mt-1.5">
                                <Mail class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#505F76]" />
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    placeholder="usuario@clinica.com"
                                    class="w-full rounded-lg border border-[#BDC9C6] bg-white py-2.5 pl-10 pr-3.5 text-sm text-[#131B2E] outline-none transition focus:border-[#005C55] focus:ring-2 focus:ring-[#005C55]"
                                />
                            </div>
                            <p v-if="form.errors.email" class="mt-1.5 text-xs font-medium text-rose-700">{{ form.errors.email }}</p>
                        </div>

                        <div class="flex gap-3 rounded-xl border border-[#D7E2DF] bg-[#F7FAF9] p-4 text-xs leading-5 text-[#505F76]">
                            <ShieldCheck class="h-5 w-5 shrink-0 text-[#0F766E]" />
                            <p>Por seguridad, mostraremos el mismo resultado exista o no una cuenta con ese correo.</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#005C55] py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#00504A] focus:outline-none focus:ring-2 focus:ring-[#005C55] focus:ring-offset-2 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Enviando…' : 'Enviar enlace seguro' }}
                            <ArrowRight class="h-4 w-4" />
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
