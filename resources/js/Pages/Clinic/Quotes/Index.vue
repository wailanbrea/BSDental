<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ChevronRight, FilePlus2, FileText, UserRound } from 'lucide-vue-next'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'

interface Patient { id: string; record_number: string; full_name: string }
interface QuoteItem { id: string; total: number; procedure: { name: string } }
interface Quote {
    id: string; quote_number: string; alternative_name: string; status: string; grand_total: number
    expires_at: string | null; created_at: string; professional: { full_name: string } | null; items: QuoteItem[]
}

defineProps<{ patient: Patient; quotes: Quote[] }>()

const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(value || 0)
const date = (value: string | null) => value ? new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(new Date(value)) : 'Sin vencimiento'
const statusLabel = (status: string) => ({ draft: 'Borrador', approved: 'Aprobado', rejected: 'Rechazado', expired: 'Vencido' } as Record<string, string>)[status] || status
</script>

<template>
    <Head :title="`Presupuestos — ${patient.full_name}`" />
    <ClinicLayout>
        <div class="mx-auto max-w-[1400px] space-y-5 p-4 md:p-7">
            <header class="flex flex-col justify-between gap-4 border-b border-[#D8E0DE] pb-5 md:flex-row md:items-end">
                <div><p class="text-xs font-bold uppercase tracking-[0.16em] text-[#006B63]">Presupuestos</p><h1 class="mt-1 text-2xl font-bold text-[#131B2E]">Alternativas de tratamiento</h1><p class="mt-1 text-sm text-[#667085]">{{ patient.full_name }} · {{ patient.record_number }}</p></div>
                <div class="flex gap-2"><Link :href="`/patients/${patient.id}`" class="inline-flex h-10 items-center border border-[#9AAEAA] bg-white px-4 text-sm font-semibold text-[#005C55]">Volver a ficha</Link><Link :href="`/patients/${patient.id}/quotes/create`" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-semibold text-white"><FilePlus2 class="h-4 w-4" /> Nuevo presupuesto</Link></div>
            </header>

            <section class="grid gap-4 lg:grid-cols-3">
                <Link v-for="quote in quotes" :key="quote.id" :href="`/quotes/${quote.id}`" class="group border border-[#BDC9C6] bg-white p-5 hover:border-[#007D73] hover:shadow-sm">
                    <div class="flex items-start justify-between gap-3"><div><p class="font-mono text-xs font-bold text-[#006B63]">{{ quote.quote_number }}</p><h2 class="mt-1 font-semibold text-[#131B2E]">{{ quote.alternative_name }}</h2></div><span class="px-2 py-1 text-xs font-semibold" :class="quote.status === 'approved' ? 'bg-[#D8ECE9] text-[#006B63]' : quote.status === 'rejected' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'">{{ statusLabel(quote.status) }}</span></div>
                    <div class="mt-5 flex items-end justify-between border-t border-[#E2E8F0] pt-4"><div><p class="text-xs text-[#667085]">{{ quote.items.length }} procedimientos · {{ date(quote.expires_at) }}</p><p class="mt-1 flex items-center gap-1 text-xs text-[#455653]"><UserRound class="h-3.5 w-3.5" /> {{ quote.professional?.full_name || 'Sin profesional asignado' }}</p><p class="mt-3 font-mono text-xl font-bold text-[#131B2E]">{{ money(quote.grand_total) }}</p></div><ChevronRight class="h-5 w-5 text-[#006B63] transition-transform group-hover:translate-x-1" /></div>
                </Link>
                <div v-if="!quotes.length" class="col-span-full border border-dashed border-[#9AAEAA] bg-white p-12 text-center"><FileText class="mx-auto h-8 w-8 text-[#667085]" /><p class="mt-3 font-semibold text-[#131B2E]">Este paciente aún no tiene presupuestos.</p><Link :href="`/patients/${patient.id}/quotes/create`" class="mt-3 inline-block text-sm font-bold text-[#006B63]">Crear el primero →</Link></div>
            </section>
        </div>
    </ClinicLayout>
</template>
