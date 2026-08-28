<script setup lang="ts">
import { computed, ref } from 'vue'
import { teethPaths } from './toothGeometry'

type SurfaceKey = 'all' | 'vestibular' | 'lingual_palatal' | 'mesial' | 'distal' | 'occlusal_incisal'
type ConditionKey = 'caries' | 'restored_composite' | 'restored_amalgam' | 'crown' | 'endodontic' | 'missing' | 'implant' | 'prosthesis' | 'sealant' | 'fracture' | 'healthy'
type LifecycleKey = 'initial_diagnosis' | 'planned' | 'approved' | 'completed'

interface ConditionSummary {
  id: string
  condition: string
  surface: string
  surfaces?: string[]
  lifecycle_state: string
  recorded_at: string
  notes: string | null
}

interface ToothData {
  tooth_number: number
  conditions: ConditionSummary[]
  surfaces: Partial<Record<SurfaceKey, { condition: ConditionKey; lifecycle_state: LifecycleKey }>>
  latest_state: LifecycleKey
}

interface PeriodontalSummary {
  sites: number
  maxProbingDepth: number | null
  bleedingSites: number
  plaqueSites: number
  suppurationSites: number
  mobility: number | null
  furcation: number | null
}

interface Point { x: number; y: number }
interface ArchGroup { quadrant: number; transform: string; mirrorX: boolean; mirrorY: boolean }
interface SurfacePatch { surface: Exclude<SurfaceKey, 'all'>; path?: string; cx?: number; cy?: number; radius?: number; fill: string; stroke: string }

const props = defineProps<{
  matrix: Record<number, ToothData>
  dentition: 'permanent' | 'primary'
  selectedTooth?: number
  periodontalByTooth?: Record<number, PeriodontalSummary>
}>()

const emit = defineEmits<{ select: [tooth: number] }>()
const hoveredTooth = ref<number | null>(null)

const conditionColors: Record<ConditionKey, { fill: string; stroke: string }> = {
  caries: { fill: '#FEE4E2', stroke: '#D92D20' },
  restored_composite: { fill: '#D1E9FF', stroke: '#1570EF' },
  restored_amalgam: { fill: '#E4E7EC', stroke: '#475467' },
  crown: { fill: '#FEF0C7', stroke: '#DC6803' },
  endodontic: { fill: '#E9D7FE', stroke: '#7F56D9' },
  missing: { fill: 'transparent', stroke: '#667085' },
  implant: { fill: '#D5D9EB', stroke: '#344054' },
  prosthesis: { fill: '#FFDFB5', stroke: '#E04F16' },
  sealant: { fill: '#D1FADF', stroke: '#039855' },
  fracture: { fill: '#FFFAEB', stroke: '#F79009' },
  healthy: { fill: '#FFFFFF', stroke: '#8FA4B2' },
}

const conditionLabels: Record<ConditionKey, string> = {
  caries: 'Caries activa',
  restored_composite: 'Restauración en resina',
  restored_amalgam: 'Restauración en amalgama',
  crown: 'Corona',
  endodontic: 'Endodoncia',
  missing: 'Pieza ausente',
  implant: 'Implante',
  prosthesis: 'Prótesis',
  sealant: 'Sellante',
  fracture: 'Fractura',
  healthy: 'Sin hallazgos',
}

const surfaceLabels: Record<SurfaceKey, string> = {
  all: '', vestibular: 'V', lingual_palatal: 'L/P', mesial: 'M', distal: 'D', occlusal_incisal: 'O/I',
}

const centers: Point[] = [
  { x: 178, y: 31 }, { x: 135, y: 36 }, { x: 103, y: 58 }, { x: 75, y: 94 },
  { x: 54, y: 124 }, { x: 38, y: 171 }, { x: 31, y: 235 }, { x: 29, y: 290 },
]

const labelPoints: Point[] = [
  { x: 178, y: 73 }, { x: 135, y: 80 }, { x: 103, y: 101 }, { x: 75, y: 130 },
  { x: 53, y: 163 }, { x: 39, y: 215 }, { x: 31, y: 274 }, { x: 29, y: 329 },
]

const permanentGroups: ArchGroup[] = [
  { quadrant: 1, transform: '', mirrorX: false, mirrorY: false },
  { quadrant: 2, transform: 'scale(-1, 1) translate(-409, 0)', mirrorX: true, mirrorY: false },
  { quadrant: 4, transform: 'scale(1, -1) translate(0, -694)', mirrorX: false, mirrorY: true },
  { quadrant: 3, transform: 'scale(-1, -1) translate(-409, -694)', mirrorX: true, mirrorY: true },
]

const primaryGroups: ArchGroup[] = [
  { quadrant: 5, transform: '', mirrorX: false, mirrorY: false },
  { quadrant: 6, transform: 'scale(-1, 1) translate(-409, 0)', mirrorX: true, mirrorY: false },
  { quadrant: 8, transform: 'scale(1, -1) translate(0, -694)', mirrorX: false, mirrorY: true },
  { quadrant: 7, transform: 'scale(-1, -1) translate(-409, -694)', mirrorX: true, mirrorY: true },
]

function mapPoint(point: Point, group: ArchGroup): Point {
  return { x: group.mirrorX ? 409 - point.x : point.x, y: group.mirrorY ? 694 - point.y : point.y }
}

const displayedTeeth = computed(() => {
  const groups = props.dentition === 'permanent' ? permanentGroups : primaryGroups
  const count = props.dentition === 'permanent' ? 8 : 5
  return groups.flatMap((group) => teethPaths.slice(0, count).map((geometry, index) => ({
    number: (group.quadrant * 10) + index + 1,
    geometry,
    transform: group.transform,
    localCenter: centers[index],
    center: mapPoint(centers[index], group),
    label: mapPoint(labelPoints[index], group),
  })))
})

function stateFor(tooth: number): ToothData | undefined { return props.matrix[tooth] }
function latestEntry(tooth: number): ConditionSummary | undefined {
  const conditions = stateFor(tooth)?.conditions ?? []
  return conditions[conditions.length - 1]
}
function normalizedCondition(value?: string): ConditionKey {
  const aliases: Record<string, ConditionKey> = {
    resin: 'restored_composite', restoration: 'restored_composite', amalgam: 'restored_amalgam',
    absent: 'missing', root_canal: 'endodontic',
  }
  if (!value) return 'healthy'
  return aliases[value] ?? (value in conditionColors ? value as ConditionKey : 'healthy')
}
function conditionFor(tooth: number): ConditionKey { return normalizedCondition(latestEntry(tooth)?.condition) }
function visualFor(tooth: number) { return conditionColors[conditionFor(tooth)] }
function hasSurfaceStates(tooth: number): boolean { return Object.keys(stateFor(tooth)?.surfaces ?? {}).some(surface => surface !== 'all') }
function wholeToothVisual(tooth: number) {
  return entrySurfaces(tooth).includes('all') || !hasSurfaceStates(tooth) ? visualFor(tooth) : conditionColors.healthy
}

function normalizedVector(from: Point, to: Point): Point {
  const dx = to.x - from.x
  const dy = to.y - from.y
  const length = Math.hypot(dx, dy) || 1
  return { x: dx / length, y: dy / length }
}

function wedgePath(center: Point, direction: Point, radius = 24): string {
  const side = { x: -direction.y, y: direction.x }
  const point = (forward: number, lateral: number) => `${center.x + direction.x * forward + side.x * lateral},${center.y + direction.y * forward + side.y * lateral}`
  return `M${center.x},${center.y} L${point(radius, radius * 0.72)} L${point(radius * 1.65, 0)} L${point(radius, -radius * 0.72)} Z`
}

function surfacePatches(tooth: { number: number; localCenter: Point }): SurfacePatch[] {
  const states = stateFor(tooth.number)?.surfaces ?? {}
  const index = tooth.number % 10 - 1
  const center = tooth.localCenter
  const mesialTarget = index > 0 ? centers[index - 1] : { x: 204.5, y: center.y }
  const directions: Record<Exclude<SurfaceKey, 'all' | 'occlusal_incisal'>, Point> = {
    mesial: normalizedVector(center, mesialTarget),
    distal: normalizedVector(mesialTarget, center),
    vestibular: normalizedVector({ x: 204.5, y: 347 }, center),
    lingual_palatal: normalizedVector(center, { x: 204.5, y: 347 }),
  }
  const patches: SurfacePatch[] = []

  for (const surface of ['mesial', 'distal', 'vestibular', 'lingual_palatal'] as const) {
    const state = states[surface]
    if (!state) continue
    const visual = conditionColors[normalizedCondition(state.condition)]
    patches.push({ surface, path: wedgePath(center, directions[surface]), fill: visual.fill, stroke: visual.stroke })
  }

  const occlusal = states.occlusal_incisal
  if (occlusal) {
    const visual = conditionColors[normalizedCondition(occlusal.condition)]
    patches.push({ surface: 'occlusal_incisal', cx: center.x, cy: center.y, radius: 8, fill: visual.fill, stroke: visual.stroke })
  }

  return patches
}

function clipId(tooth: number): string { return `tooth-surface-clip-${props.dentition}-${tooth}` }
function lifecycleFor(tooth: number): LifecycleKey {
  const value = latestEntry(tooth)?.lifecycle_state
  return value === 'planned' || value === 'approved' || value === 'completed' ? value : 'initial_diagnosis'
}

function normalizedSurface(value?: string): SurfaceKey {
  if (value === 'occlusal' || value === 'incisal') return 'occlusal_incisal'
  if (value === 'lingual' || value === 'palatal') return 'lingual_palatal'
  if (value && value in surfaceLabels) return value as SurfaceKey
  return 'all'
}

function ariaLabel(tooth: number): string {
  const entry = latestEntry(tooth)
  const condition = conditionLabels[conditionFor(tooth)]
  const normalized = normalizedSurface(entry?.surface)
  const surface = normalized !== 'all' ? `, superficie ${surfaceLabels[normalized]}` : ''
  return `Pieza FDI ${tooth}, ${condition}${surface}${props.selectedTooth === tooth ? ', seleccionada' : ''}`
}

function statusSymbol(tooth: number): string {
  const condition = conditionFor(tooth)
  if (condition === 'missing') return '×'
  if (condition === 'implant') return 'I'
  if (condition === 'endodontic') return 'E'
  return ''
}

function entrySurfaces(tooth: number): SurfaceKey[] {
  const entry = latestEntry(tooth)
  return (entry?.surfaces?.length ? entry.surfaces : [entry?.surface]).map(normalizedSurface)
}

function surfaceSymbol(tooth: number): string {
  const values = entrySurfaces(tooth).filter(surface => surface !== 'all')
  if (values.length > 1) return String(values.length)
  return values[0] ? surfaceLabels[values[0]] : ''
}

const surfaceFullLabels: Record<SurfaceKey, string> = {
  all: 'Pieza completa',
  vestibular: 'Vestibular',
  lingual_palatal: 'Lingual / palatina',
  mesial: 'Mesial',
  distal: 'Distal',
  occlusal_incisal: 'Oclusal / incisal',
}

const lifecycleLabels: Record<LifecycleKey, string> = {
  initial_diagnosis: 'Diagnóstico',
  planned: 'Planificado',
  approved: 'Aprobado',
  completed: 'Realizado',
}

function selectTooth(tooth: number): void {
  hoveredTooth.value = tooth
  emit('select', tooth)
}

function historyCount(tooth: number): number { return stateFor(tooth)?.conditions.length ?? 0 }
function tooltipSurface(tooth: number): string { return entrySurfaces(tooth).map(surface => surfaceFullLabels[surface]).join(' · ') }
function tooltipLifecycle(tooth: number): string { return lifecycleLabels[lifecycleFor(tooth)] }
function tooltipDate(tooth: number): string | null {
  const value = latestEntry(tooth)?.recorded_at
  if (!value) return null
  return new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}
function periodontalFor(tooth: number): PeriodontalSummary | undefined { return props.periodontalByTooth?.[tooth] }
function periodontalFlags(summary: PeriodontalSummary): string {
  const flags = [
    summary.bleedingSites ? `BOP ${summary.bleedingSites}/6` : null,
    summary.plaqueSites ? `Placa ${summary.plaqueSites}/6` : null,
    summary.suppurationSites ? `Supuración ${summary.suppurationSites}/6` : null,
  ].filter(Boolean)
  return flags.length ? flags.join(' · ') : 'Sin BOP, placa ni supuración'
}
function tooltipStyle(tooth: number): Record<string, string> {
  const current = displayedTeeth.value.find((item) => item.number === tooth)
  if (!current) return {}
  const x = (current.center.x / 409) * 100
  const y = (current.center.y / 694) * 100
  return {
    left: `${x}%`,
    top: `${y}%`,
    transform: `translate(${x > 68 ? '-104%' : x < 32 ? '4%' : '-50%'}, ${y > 62 ? '-104%' : '12%'})`,
  }
}
</script>

<template>
  <div class="odontogram-shell">
    <div class="odontogram-heading" aria-hidden="true"><span>Superior</span><span>Notación FDI</span></div>
    <div class="odontogram-canvas">
    <svg class="odontogram-svg" viewBox="0 0 409 694" role="group" :aria-label="`Odontograma ${dentition === 'permanent' ? 'permanente de 32 piezas' : 'temporal de 20 piezas'}`">
      <defs>
        <filter id="selected-tooth-glow" x="-35%" y="-35%" width="170%" height="170%"><feDropShadow dx="0" dy="0" stdDeviation="4" flood-color="#008F83" flood-opacity="0.35" /></filter>
        <linearGradient id="palate-wash" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#FFF8F7" /><stop offset="1" stop-color="#F8EEEC" /></linearGradient>
        <linearGradient id="tongue-wash" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F6F8F8" /><stop offset="1" stop-color="#EEF2F1" /></linearGradient>
        <clipPath v-for="tooth in displayedTeeth" :id="clipId(tooth.number)" :key="`clip-${tooth.number}`" clipPathUnits="userSpaceOnUse"><path :d="tooth.geometry.shadowPath" /></clipPath>
      </defs>

      <path class="anatomy-zone" d="M94 243 Q204.5 79 315 243 Q204.5 309 94 243Z" fill="url(#palate-wash)" />
      <path class="anatomy-zone" d="M94 451 Q204.5 385 315 451 Q204.5 615 94 451Z" fill="url(#tongue-wash)" />
      <path class="arch-guide" d="M204.5 77 V617" />
      <text x="204.5" y="218" class="anatomy-label">PALADAR</text>
      <text x="204.5" y="488" class="anatomy-label">LENGUA</text>

      <g v-for="tooth in displayedTeeth" :key="tooth.number" :transform="tooth.transform" :class="['tooth', `condition-${conditionFor(tooth.number)}`, `lifecycle-${lifecycleFor(tooth.number)}`, { selected: selectedTooth === tooth.number }]" role="button" tabindex="0" :aria-label="ariaLabel(tooth.number)" :aria-pressed="selectedTooth === tooth.number" @mouseenter="hoveredTooth = tooth.number" @mouseleave="hoveredTooth = null" @focus="hoveredTooth = tooth.number" @blur="hoveredTooth = null" @click="selectTooth(tooth.number)" @keydown.enter.prevent="selectTooth(tooth.number)" @keydown.space.prevent="selectTooth(tooth.number)">
        <path class="tooth-fill" :d="tooth.geometry.shadowPath" :fill="wholeToothVisual(tooth.number).fill" />
        <g :clip-path="`url(#${clipId(tooth.number)})`" class="tooth-surface-layer" aria-hidden="true">
          <template v-for="patch in surfacePatches(tooth)" :key="`${tooth.number}-${patch.surface}`">
            <path v-if="patch.path" class="tooth-surface-patch" :d="patch.path" :fill="patch.fill" :stroke="patch.stroke" />
            <circle v-else class="tooth-surface-patch" :cx="patch.cx" :cy="patch.cy" :r="patch.radius" :fill="patch.fill" :stroke="patch.stroke" />
          </template>
        </g>
        <path class="tooth-outline" :d="tooth.geometry.outlinePath" :stroke="wholeToothVisual(tooth.number).stroke" />
        <path v-for="detail in Array.isArray(tooth.geometry.lineHighlightPath) ? tooth.geometry.lineHighlightPath : [tooth.geometry.lineHighlightPath]" :key="detail" class="tooth-detail" :d="detail" :stroke="wholeToothVisual(tooth.number).stroke" />
      </g>

      <g v-for="tooth in displayedTeeth" :key="`overlay-${tooth.number}`" class="tooth-overlay" aria-hidden="true">
        <circle v-if="surfaceSymbol(tooth.number) && !hasSurfaceStates(tooth.number)" :cx="tooth.center.x" :cy="tooth.center.y" r="9" :fill="visualFor(tooth.number).stroke" class="surface-badge" />
        <text v-if="surfaceSymbol(tooth.number) && !hasSurfaceStates(tooth.number)" :x="tooth.center.x" :y="tooth.center.y + 0.5" class="surface-symbol">{{ surfaceSymbol(tooth.number) }}</text>
        <text v-else-if="statusSymbol(tooth.number)" :x="tooth.center.x" :y="tooth.center.y + 1" class="status-symbol" :fill="visualFor(tooth.number).stroke">{{ statusSymbol(tooth.number) }}</text>
        <rect v-if="selectedTooth === tooth.number" :x="tooth.label.x - 15" :y="tooth.label.y - 11" width="30" height="22" rx="11" class="selected-label-bg" />
        <text :x="tooth.label.x" :y="tooth.label.y" class="tooth-number" :class="{ selected: selectedTooth === tooth.number }">{{ tooth.number }}</text>
      </g>
    </svg>
    <aside v-if="hoveredTooth !== null" class="tooth-tooltip" :style="tooltipStyle(hoveredTooth)" role="status">
      <div class="tooth-tooltip__header">
        <div><span class="tooth-tooltip__eyebrow">Ficha dental</span><strong>Pieza FDI {{ hoveredTooth }}</strong></div>
        <span class="tooth-tooltip__count">{{ historyCount(hoveredTooth) }} registro{{ historyCount(hoveredTooth) === 1 ? '' : 's' }}</span>
      </div>
      <template v-if="latestEntry(hoveredTooth)">
        <dl class="tooth-tooltip__details">
          <div><dt>Condición</dt><dd>{{ conditionLabels[conditionFor(hoveredTooth)] }}</dd></div>
          <div><dt>Superficie</dt><dd>{{ tooltipSurface(hoveredTooth) }}</dd></div>
          <div><dt>Estado</dt><dd>{{ tooltipLifecycle(hoveredTooth) }}</dd></div>
          <div v-if="tooltipDate(hoveredTooth)"><dt>Registrado</dt><dd>{{ tooltipDate(hoveredTooth) }}</dd></div>
        </dl>
        <p v-if="latestEntry(hoveredTooth)?.notes" class="tooth-tooltip__notes">{{ latestEntry(hoveredTooth)?.notes }}</p>
      </template>
      <p v-else class="tooth-tooltip__empty">Sin registros clínicos. Selecciona la pieza para documentarla.</p>
      <div v-if="periodontalFor(hoveredTooth)" class="tooth-tooltip__periodontal">
        <strong>Resumen periodontal</strong>
        <span>{{ periodontalFor(hoveredTooth)?.sites }}/6 sitios · Sondaje máx. {{ periodontalFor(hoveredTooth)?.maxProbingDepth ?? '—' }} mm</span>
        <span>{{ periodontalFlags(periodontalFor(hoveredTooth)!) }}</span>
        <span>Movilidad {{ periodontalFor(hoveredTooth)?.mobility ?? '—' }} · Furca {{ periodontalFor(hoveredTooth)?.furcation ?? '—' }}</span>
      </div>
      <p class="tooth-tooltip__hint">El detalle guardado aparece en “Trazabilidad del odontograma”.</p>
    </aside>
    </div>
    <div class="odontogram-heading odontogram-heading--lower" aria-hidden="true"><span>Inferior</span><span>Haz clic o usa Enter para seleccionar</span></div>
  </div>
</template>

<style scoped>
.odontogram-shell { width: 100%; max-width: 760px; margin-inline: auto; border: 1px solid #d8e0de; background: #fff; box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); }
.odontogram-heading { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid #e4e7ec; color: #52615e; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; }
.odontogram-heading--lower { border-top: 1px solid #e4e7ec; border-bottom: 0; letter-spacing: 0.04em; text-transform: none; }
.odontogram-canvas { position: relative; width: min(100%, 650px); margin-inline: auto; }
.odontogram-svg { display: block; width: min(100%, 650px); height: auto; margin-inline: auto; overflow: visible; background: #fff; touch-action: manipulation; user-select: none; }
.anatomy-zone { stroke: #e7dfdd; stroke-width: 1; }
.arch-guide { fill: none; stroke: #d9e2e0; stroke-width: 0.8; stroke-dasharray: 3 5; }
.anatomy-label { fill: #98a2b3; font-size: 9px; font-weight: 700; letter-spacing: 0.16em; text-anchor: middle; }
.tooth { cursor: pointer; outline: none; }
.tooth-fill { opacity: 0.72; transition: fill 160ms ease, opacity 160ms ease; }
.tooth-surface-patch { stroke-width: 1.4; opacity: 0.94; vector-effect: non-scaling-stroke; }
.tooth-outline, .tooth-detail { fill: none; stroke-linecap: round; stroke-linejoin: round; transition: stroke 160ms ease, stroke-width 160ms ease, opacity 160ms ease; }
.tooth-outline { stroke-width: 2; }
.tooth-detail { stroke-width: 1; opacity: 0.75; }
.tooth:hover .tooth-fill, .tooth:focus-visible .tooth-fill { opacity: 1; }
.tooth:hover .tooth-outline, .tooth:focus-visible .tooth-outline { stroke: #005c55; stroke-width: 3; }
.tooth.selected { filter: url(#selected-tooth-glow); }
.tooth.selected .tooth-outline { stroke: #005c55; stroke-width: 3.5; }
.tooth.lifecycle-planned .tooth-outline { stroke-dasharray: 6 3; }
.tooth.lifecycle-approved .tooth-outline { stroke-width: 2.8; }
.tooth.lifecycle-completed .tooth-fill { opacity: 0.94; }
.tooth.condition-missing .tooth-fill { opacity: 0; }
.tooth.condition-missing .tooth-outline { stroke-dasharray: 4 4; opacity: 0.65; }
.tooth.condition-missing .tooth-detail { opacity: 0.2; }
.tooth-overlay { pointer-events: none; }
.tooth-number, .surface-symbol, .status-symbol { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; text-anchor: middle; dominant-baseline: middle; pointer-events: none; }
.tooth-number { fill: #344054; font-size: 11px; font-weight: 700; }
.tooth-number.selected { fill: #fff; }
.selected-label-bg { fill: #005c55; }
.surface-badge { stroke: #fff; stroke-width: 1.5; }
.surface-symbol { fill: #fff; font-size: 6px; font-weight: 800; }
.status-symbol { font-size: 18px; font-weight: 800; }
.tooth-tooltip { position: absolute; z-index: 20; width: min(280px, calc(100vw - 48px)); border: 1px solid #9aaEaa; background: rgba(255, 255, 255, 0.98); padding: 12px; color: #344054; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18); pointer-events: none; }
.tooth-tooltip__header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.tooth-tooltip__header strong { display: block; margin-top: 2px; color: #005c55; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 16px; }
.tooth-tooltip__eyebrow { display: block; color: #667085; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; }
.tooth-tooltip__count { border: 1px solid #b7d4cf; background: #edf8f6; padding: 3px 6px; color: #005c55; font-size: 9px; font-weight: 800; white-space: nowrap; }
.tooth-tooltip__details { display: grid; gap: 5px; margin-top: 10px; }
.tooth-tooltip__details div { display: grid; grid-template-columns: 70px 1fr; gap: 8px; }
.tooth-tooltip__details dt { color: #667085; font-size: 10px; }
.tooth-tooltip__details dd { margin: 0; font-size: 11px; font-weight: 700; }
.tooth-tooltip__notes { margin: 9px 0 0; border-left: 2px solid #8fbeb7; padding-left: 8px; color: #52615e; font-size: 10px; line-height: 1.45; }
.tooth-tooltip__empty { margin: 10px 0 0; color: #52615e; font-size: 11px; line-height: 1.45; }
.tooth-tooltip__periodontal { display: grid; gap: 3px; margin-top: 10px; border: 1px solid #fedf89; background: #fffcf5; padding: 8px; color: #52615e; font-size: 9px; line-height: 1.4; }
.tooth-tooltip__periodontal strong { color: #b54708; font-size: 10px; }
.tooth-tooltip__hint { margin: 10px 0 0; border-top: 1px solid #e4e7ec; padding-top: 8px; color: #667085; font-size: 9px; line-height: 1.4; }
@media (max-width: 640px) { .odontogram-heading { padding-inline: 12px; } .odontogram-svg { min-width: 390px; } .odontogram-shell { overflow-x: auto; } }
@media (prefers-reduced-motion: reduce) { .tooth-fill, .tooth-outline, .tooth-detail { transition: none; } }
</style>
