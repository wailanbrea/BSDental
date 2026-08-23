<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    Activity, 
    ArrowLeft, 
    Building2, 
    Layers, 
    User 
} from 'lucide-vue-next';

interface PlanItem {
    id: string;
    name: string;
    description?: string | null;
    modules: string[];
}

interface Props {
    plans: PlanItem[];
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    slug: '',
    domain: '',
    plan_id: props.plans[0]?.id || 'starter',
    owner_name: '',
    owner_email: '',
    owner_password: '',
    currency: 'USD',
    timezone: 'UTC',
});

const handleSlugChange = () => {
    if (form.name && !form.slug) {
        form.slug = form.name.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        form.domain = `${form.slug}.bsdental.app`;
    }
};

const submit = () => {
    form.post('/platform/tenants');
};
</script>

<template>
    <Head title="Platform — Aprovisionar Nueva Clínica" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-30">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <Link href="/platform/tenants" class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:text-white transition">
                        <ArrowLeft class="w-4 h-4" />
                    </Link>
                    <div class="w-8 h-8 rounded-xl bg-teal-600 flex items-center justify-center text-white">
                        <Activity class="w-5 h-5" />
                    </div>
                    <span class="font-bold text-base text-white">Nueva Organización</span>
                </div>
            </div>
        </header>

        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
            <div class="bg-slate-900 border border-slate-800 p-8 rounded-2xl shadow-xl">
                <h1 class="text-xl font-bold text-white mb-2">Aprovisionar Organización Tenant</h1>
                <p class="text-xs text-slate-400 mb-8">El pipeline ejecutará automáticamente la creación física de la BD, migraciones, seeding de roles clínicos y creación del Owner.</p>

                <form class="space-y-6" @submit.prevent="submit">
                    <!-- General Clinic Info -->
                    <div class="space-y-4">
                        <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                            <Building2 class="w-4 h-4" /> Información de la Clínica
                        </h2>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Nombre Comercial *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Ej: Clínica Dental Las Palmas"
                                    class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                                    @blur="handleSlugChange"
                                />
                                <div v-if="form.errors.name" class="text-rose-400 text-xs mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Slug / Identificador *</label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    required
                                    placeholder="ej: las-palmas"
                                    class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                                />
                                <div v-if="form.errors.slug" class="text-rose-400 text-xs mt-1">{{ form.errors.slug }}</div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Dominio Primario de Acceso *</label>
                            <input
                                v-model="form.domain"
                                type="text"
                                required
                                placeholder="ej: las-palmas.bsdental.app"
                                class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500 font-mono"
                            />
                            <div v-if="form.errors.domain" class="text-rose-400 text-xs mt-1">{{ form.errors.domain }}</div>
                        </div>
                    </div>

                    <!-- Commercial Plan -->
                    <div class="space-y-4 pt-4 border-t border-slate-800">
                        <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                            <Layers class="w-4 h-4" /> Plan Comercial & Entitlements
                        </h2>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Seleccionar Plan *</label>
                            <select
                                v-model="form.plan_id"
                                class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                            >
                                <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                                    {{ plan.name }} ({{ plan.modules.length }} módulos)
                                </option>
                            </select>
                            <div v-if="form.errors.plan_id" class="text-rose-400 text-xs mt-1">{{ form.errors.plan_id }}</div>
                        </div>
                    </div>

                    <!-- Owner Credentials -->
                    <div class="space-y-4 pt-4 border-t border-slate-800">
                        <h2 class="text-xs font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2">
                            <User class="w-4 h-4" /> Propietario / Director Médico (Owner)
                        </h2>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nombre Completo del Director *</label>
                            <input
                                v-model="form.owner_name"
                                type="text"
                                required
                                placeholder="Ej: Dra. Carmen Morales"
                                class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                            />
                            <div v-if="form.errors.owner_name" class="text-rose-400 text-xs mt-1">{{ form.errors.owner_name }}</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Correo Electrónico *</label>
                                <input
                                    v-model="form.owner_email"
                                    type="email"
                                    required
                                    placeholder="carmen@laspalmasdental.com"
                                    class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                                />
                                <div v-if="form.errors.owner_email" class="text-rose-400 text-xs mt-1">{{ form.errors.owner_email }}</div>
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Contraseña Inicial *</label>
                                <input
                                    v-model="form.owner_password"
                                    type="password"
                                    required
                                    placeholder="••••••••"
                                    class="w-full px-3 py-2 text-xs bg-slate-950 border border-slate-700 rounded-lg text-white placeholder-slate-500 focus:outline-hidden focus:ring-1 focus:ring-teal-500"
                                />
                                <div v-if="form.errors.owner_password" class="text-rose-400 text-xs mt-1">{{ form.errors.owner_password }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-6 border-t border-slate-800 flex justify-end gap-3">
                        <Link
                            href="/platform/tenants"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-semibold transition"
                        >
                            Cancelar
                        </Link>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-teal-600 hover:bg-teal-500 disabled:opacity-50 text-white rounded-lg text-xs font-bold transition flex items-center gap-2"
                        >
                            <Activity v-if="form.processing" class="w-4 h-4 animate-spin" />
                            <span>{{ form.processing ? 'Aprovisionando Pipeline...' : 'Ejecutar Aprovisionamiento' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</template>