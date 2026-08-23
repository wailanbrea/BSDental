<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { Settings, Save, ArrowLeft, Building, Globe, DollarSign, Phone, MapPin } from 'lucide-vue-next'

interface TenantData {
    id: string
    name: string
    slug: string
    status: string
    settings: {
        currency: string
        timezone: string
        phone?: string
        address?: string
    }
}

const props = defineProps<{
    tenant: TenantData
}>()

const form = useForm({
    name: props.tenant.name,
    currency: props.tenant.settings.currency || 'USD',
    timezone: props.tenant.settings.timezone || 'America/Caracas',
    phone: props.tenant.settings.phone || '',
    address: props.tenant.settings.address || '',
})

function submit() {
    form.put('/settings')
}
</script>

<template>
    <ClinicLayout>
<div class="clinical-precision-page">
    <Head title="Configuración de la Clínica — BSDental" />

    <div class="min-h-screen bg-slate-900 text-slate-100 p-8">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-3 bg-teal-500/10 rounded-xl text-teal-400 border border-teal-500/20">
                        <Settings class="w-6 h-6" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-white tracking-tight">Configuración de la Clínica</h1>
                        <p class="text-sm text-slate-400">Datos fiscales, moneda base, zona horaria e identidad de la organización</p>
                    </div>
                </div>

                <a href="/dashboard" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 hover:text-white transition">
                    <ArrowLeft class="w-4 h-4" /> Volver al Dashboard
                </a>
            </div>

            <!-- Form -->
            <form class="p-8 bg-slate-800/80 border border-slate-700/60 rounded-2xl shadow-xl space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Nombre Comercial de la Clínica</label>
                        <div class="relative">
                            <Building class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" />
                            <input v-model="form.name" type="text" required class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Moneda Principal</label>
                        <div class="relative">
                            <DollarSign class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" />
                            <select v-model="form.currency" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-teal-500 focus:outline-none">
                                <option value="USD">USD ($) - Dólar Estadounidense</option>
                                <option value="EUR">EUR (€) - Euro</option>
                                <option value="VES">VES (Bs.) - Bolívar Digital</option>
                                <option value="COP">COP ($) - Peso Colombiano</option>
                                <option value="MXN">MXN ($) - Peso Mexicano</option>
                                <option value="CLP">CLP ($) - Peso Chileno</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Zona Horaria</label>
                        <div class="relative">
                            <Globe class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" />
                            <select v-model="form.timezone" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-teal-500 focus:outline-none">
                                <option value="America/Caracas">America/Caracas (UTC-4)</option>
                                <option value="America/Bogota">America/Bogota (UTC-5)</option>
                                <option value="America/Mexico_City">America/Mexico_City (UTC-6)</option>
                                <option value="America/Santiago">America/Santiago (UTC-3)</option>
                                <option value="America/Buenos_Aires">America/Buenos_Aires (UTC-3)</option>
                                <option value="America/New_York">America/New_York (UTC-5/UTC-4)</option>
                                <option value="Europe/Madrid">Europe/Madrid (UTC+1/UTC+2)</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Teléfono de Contacto Central</label>
                        <div class="relative">
                            <Phone class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" />
                            <input v-model="form.phone" type="text" placeholder="+58 212 555-0000" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Dirección Sede Central</label>
                        <div class="relative">
                            <MapPin class="w-4 h-4 text-slate-500 absolute left-3.5 top-3.5" />
                            <input v-model="form.address" type="text" placeholder="Av. Principal" class="w-full pl-10 pr-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:border-teal-500 focus:outline-none" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-700/60">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-2 px-6 py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 font-semibold rounded-xl shadow-lg shadow-teal-500/20 transition disabled:opacity-50"
                    >
                        <Save class="w-4 h-4" /> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>
</ClinicLayout>
</template>
