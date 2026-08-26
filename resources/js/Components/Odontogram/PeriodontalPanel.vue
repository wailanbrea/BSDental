<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Activity, Eraser, Info, Save } from 'lucide-vue-next'
import { computed, watch } from 'vue'
import { appUrl } from '@/lib/url'

interface Measurement {
    tooth_number: number
    site: SiteKey
    probing_depth: number | null
    recession: number | null
    bleeding: boolean
    plaque: boolean
    suppuration: boolean
    mobility: number | null
    furcation: number | null
    is_implant: boolean
}
interface Exam { id: string; status: string; recorded_at: string; measurements: Measurement[] }
type SiteKey = 'mb' | 'b' | 'db' | 'ml' | 'l' | 'dl'

const props = defineProps<{ patientId: string; selectedTooth: number; exam: Exam | null }>()
const sites: Array<{ value: SiteKey; label: string }> = [
    { value: 'mb', label: 'Mesiovestibular' }, { value: 'b', label: 'Vestibular' }, { value: 'db', label: 'Distovestibular' },
    { value: 'ml', label: 'Mesiolingual' }, { value: 'l', label: 'Lingual / palatino' }, { value: 'dl', label: 'Distolingual' },
]

function emptyMeasurements(): Measurement[] {
    return sites.map(({ value }) => ({ tooth_number: props.selectedTooth, site: value, probing_depth: null, recession: null, bleeding: false, plaque: false, suppuration: false, mobility: null, furcation: null, is_implant: false }))
}

const form = useForm({ tooth_number: props.selectedTooth, measurements: emptyMeasurements() })
const savedMeasurementCount = computed(() => props.exam?.measurements.filter((item) => item.tooth_number === props.selectedTooth).length ?? 0)

function loadTooth(tooth: number) {
    form.tooth_number = tooth
    form.measurements = emptyMeasurements().map((blank) => {
        const saved = props.exam?.measurements.find((item) => item.tooth_number === tooth && item.site === blank.site)
        return saved ? { ...blank, ...saved } : blank
    })
    form.clearErrors()
}

function clearMeasurements() {
    form.measurements = emptyMeasurements()
    form.clearErrors()
}

watch(() => [props.selectedTooth, props.exam] as const, () => loadTooth(props.selectedTooth), { immediate: true })
function submit() { form.post(appUrl(`/patients/${props.patientId}/odontogram/periodontal-measurements`), { preserveScroll: true }) }
</script>

<template>
    <section class="border border-[#D8E0DE] bg-white shadow-[0_4px_12px_rgba(15,23,42,0.04)]">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-[#D8E0DE] bg-[#F7FAF9] px-4 py-3">
            <div><h2 class="flex items-center gap-2 text-sm font-bold"><Activity class="h-4 w-4 text-[#005C55]" />Periodontograma · pieza {{ selectedTooth }}</h2><p class="mt-0.5 text-xs text-[#64748B]">Sondaje de seis sitios. BOP = sangrado al sondaje.</p></div>
            <div class="flex flex-wrap items-center gap-2"><button type="button" class="inline-flex h-8 items-center gap-1.5 border border-[#D0D5DD] bg-white px-3 text-[11px] font-bold text-[#667085] hover:bg-[#F2F4F7]" @click="clearMeasurements"><Eraser class="h-3.5 w-3.5" />Limpiar</button><span class="border border-[#B7D9D4] bg-[#F1FAF8] px-2 py-1 text-[10px] font-bold uppercase text-[#006B63]">{{ savedMeasurementCount ? `${savedMeasurementCount} mediciones guardadas` : 'Sin mediciones guardadas' }}</span></div>
        </header>
        <div class="grid gap-3 border-b border-[#D8E0DE] bg-[#FFFCF5] px-4 py-3 text-[11px] text-[#52615E] lg:grid-cols-[1.4fr_1fr]">
            <p class="flex gap-2"><Info class="mt-0.5 h-4 w-4 shrink-0 text-[#B54708]" /><span><strong class="text-[#344054]">Cómo leerlo:</strong> el sondaje mide la profundidad entre encía y diente; 1-3 mm suele ser compatible con salud. Un valor de 4 mm o más, especialmente con BOP, placa o supuración, requiere valoración profesional.</span></p>
            <p><strong class="text-[#344054]">Escalas:</strong> recesión = encía desplazada en mm; movilidad 0-3; furca 0-3. Al seleccionar una pieza, sus mediciones guardadas se cargan automáticamente.</p>
        </div>
        <form class="overflow-x-auto" @submit.prevent="submit">
            <table class="w-full min-w-[780px] text-left text-xs">
                <thead class="border-b border-[#D8E0DE] bg-[#FBFCFC] text-[10px] uppercase tracking-wide text-[#64748B]"><tr><th class="px-4 py-2">Sitio</th><th class="px-2 py-2">Sondaje mm</th><th class="px-2 py-2">Recesión mm</th><th class="px-2 py-2 text-center">BOP</th><th class="px-2 py-2 text-center">Placa</th><th class="px-2 py-2 text-center">Supuración</th><th class="px-2 py-2">Movilidad</th><th class="px-2 py-2">Furca</th></tr></thead>
                <tbody class="divide-y divide-[#E4E7EC]"><tr v-for="(measurement, index) in form.measurements" :key="measurement.site" :class="Number(measurement.probing_depth) >= 4 ? 'bg-[#FFFAEB]' : ''"><td class="px-4 py-2 font-semibold">{{ sites[index].label }}</td><td class="px-2 py-2"><input v-model.number="measurement.probing_depth" :aria-label="`Sondaje ${sites[index].label}`" type="number" min="0" max="15" placeholder="2-3" class="h-9 w-20 border px-2" :class="Number(measurement.probing_depth) >= 4 ? 'border-[#F79009] bg-white font-bold text-[#B54708]' : 'border-[#9AAEAA]'" /></td><td class="px-2 py-2"><input v-model.number="measurement.recession" :aria-label="`Recesión ${sites[index].label}`" type="number" min="-10" max="15" placeholder="0" class="h-9 w-20 border border-[#9AAEAA] px-2" /></td><td class="px-2 py-2 text-center"><input v-model="measurement.bleeding" :aria-label="`BOP ${sites[index].label}`" type="checkbox" class="h-4 w-4 accent-[#D92D20]" /></td><td class="px-2 py-2 text-center"><input v-model="measurement.plaque" :aria-label="`Placa ${sites[index].label}`" type="checkbox" class="h-4 w-4 accent-[#EAAA08]" /></td><td class="px-2 py-2 text-center"><input v-model="measurement.suppuration" :aria-label="`Supuración ${sites[index].label}`" type="checkbox" class="h-4 w-4 accent-[#B42318]" /></td><td class="px-2 py-2"><input v-model.number="measurement.mobility" :aria-label="`Movilidad ${sites[index].label}`" type="number" min="0" max="3" placeholder="0" class="h-9 w-16 border border-[#9AAEAA] px-2" /></td><td class="px-2 py-2"><input v-model.number="measurement.furcation" :aria-label="`Furca ${sites[index].label}`" type="number" min="0" max="3" placeholder="0" class="h-9 w-16 border border-[#9AAEAA] px-2" /></td></tr></tbody>
            </table>
            <div class="flex items-center justify-between gap-3 border-t border-[#D8E0DE] p-3"><p class="text-[11px] text-[#64748B]">Valores 4 mm o mayores requieren evaluación periodontal.</p><button type="submit" :disabled="form.processing" class="inline-flex h-9 items-center gap-2 bg-[#005C55] px-4 text-xs font-bold text-white disabled:opacity-60"><Save class="h-3.5 w-3.5" />Guardar sondaje</button></div>
        </form>
    </section>
</template>
