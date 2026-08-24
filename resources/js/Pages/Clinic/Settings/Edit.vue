<script setup lang="ts">
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
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
        <Head title="Configuración de la Clínica — BSDental" />

        <div class="space-y-6 max-w-4xl mx-auto">
            <!-- Header Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-[#005C55]/10 text-[#005C55] rounded-lg">
                            <Settings class="w-5 h-5" />
                        </span>
                        <h1 class="font-display-md text-2xl font-bold text-[#131B2E]">
                            Configuración de la Clínica
                        </h1>
                    </div>
                    <p class="text-xs text-[#505F76] mt-1">
                        Datos de la organización, moneda base de facturación y zona horaria operativa
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link href="/dashboard" class="px-3.5 py-2 text-xs font-medium text-[#505F76] hover:text-[#131B2E] transition">
                        ← Dashboard
                    </Link>
                </div>
            </div>

            <!-- Form -->
            <form class="p-6 bg-white border border-[#E2E8F0] rounded-xl shadow-xs space-y-6" @submit.prevent="submit">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-[#505F76] mb-1">Nombre Comercial de la Clínica *</label>
                        <div class="relative">
                            <Building class="w-4 h-4 text-[#505F76] absolute left-3.5 top-3" />
                            <input v-model="form.name" type="text" required class="w-full pl-10 pr-4 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#505F76] mb-1">Moneda Principal</label>
                        <div class="relative">
                            <DollarSign class="w-4 h-4 text-[#505F76] absolute left-3.5 top-3" />
                            <select v-model="form.currency" class="w-full pl-10 pr-4 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
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
                        <label class="block text-xs font-semibold text-[#505F76] mb-1">Zona Horaria</label>
                        <div class="relative">
                            <Globe class="w-4 h-4 text-[#505F76] absolute left-3.5 top-3" />
                            <select v-model="form.timezone" class="w-full pl-10 pr-4 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]">
                                <option value="America/Caracas">America/Caracas (UTC-4)</option>
                                <option value="America/Santo_Domingo">America/Santo_Domingo (UTC-4)</option>
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
                        <label class="block text-xs font-semibold text-[#505F76] mb-1">Teléfono de Contacto Central</label>
                        <div class="relative">
                            <Phone class="w-4 h-4 text-[#505F76] absolute left-3.5 top-3" />
                            <input v-model="form.phone" type="text" placeholder="+58 212 555-0000" class="w-full pl-10 pr-4 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#505F76] mb-1">Dirección Sede Central</label>
                        <div class="relative">
                            <MapPin class="w-4 h-4 text-[#505F76] absolute left-3.5 top-3" />
                            <input v-model="form.address" type="text" placeholder="Av. Principal, Edif. Médico" class="w-full pl-10 pr-4 py-2 bg-[#F8FAFC] border border-[#BDC9C6] rounded-lg text-[#131B2E] text-xs focus:bg-white focus:border-[#005C55]" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-[#E2E8F0]">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex items-center gap-1.5 px-4 py-2 bg-[#005C55] hover:bg-[#004742] text-white font-bold text-xs rounded-lg transition disabled:opacity-50 shadow-xs"
                    >
                        <Save class="w-4 h-4" /> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </ClinicLayout>
</template>
