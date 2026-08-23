<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound, ShieldCheck } from 'lucide-vue-next';

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/two-factor', {
        onFinish: () => {
            form.reset('code');
        },
    });
};
</script>

<template>
    <Head title="Verificación 2FA — Acceso Clínico" />

    <div class="min-h-screen bg-slate-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-teal-50 text-teal-700 border border-teal-200 mb-4">
                <ShieldCheck class="w-6 h-6" />
            </div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                Verificación de Seguridad
            </h2>
            <p class="mt-1 text-xs text-slate-500">
                Ingresa el código 2FA generado en tu dispositivo.
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-slate-200">
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="code" class="block text-xs font-semibold text-slate-700 mb-1">
                            Código de Autenticación
                        </label>
                        <div class="relative rounded-lg shadow-xs">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                                <KeyRound class="h-4 w-4" />
                            </div>
                            <input
                                id="code"
                                v-model="form.code"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                required
                                autofocus
                                class="block w-full pl-9 pr-3 py-2 text-center tracking-widest text-lg font-mono bg-white border border-slate-300 rounded-lg text-slate-900 focus:outline-hidden focus:ring-2 focus:ring-teal-600 transition"
                                placeholder="123456"
                            />
                        </div>
                        <p v-if="form.errors.code" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.code }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-2.5 px-4 rounded-lg font-semibold text-sm text-white bg-teal-600 hover:bg-teal-700 transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Validando...' : 'Verificar e Ingresar' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>