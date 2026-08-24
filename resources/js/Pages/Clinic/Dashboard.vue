<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { computed } from 'vue'
import { 
    Calendar, 
    UserCheck, 
    CreditCard, 
    Wallet, 
    TrendingUp, 
    AlertTriangle, 
    Clock, 
    Users, 
    Package, 
    ArrowRight,
    FlaskConical,
    Boxes,
    AlertCircle,
    CheckCircle2,
    Plus,
    FileText,
    ReceiptText,
    Stethoscope,
    MessageCircle,
    DollarSign,
    ShieldAlert,
    Activity,
    Layers
} from 'lucide-vue-next'

interface Props {
    clinic: {
        name: string
        trade_name: string
        currency: string
        timezone: string
    }
    user: {
        id: string
        name: string
        email: string
        phone?: string | null
        roles: string[]
        permissions: string[]
        primary_role: 'Owner' | 'Dentist' | 'Receptionist' | 'Cashier' | 'InventoryManager' | 'LabTechnician' | 'Restricted'
    }
    kpis: {
        appointments_today: number
        appointments_trend: string
        patients_attended_today: number
        attended_trend: string
        net_collected_today: number
        collected_trend: string
        accounts_receivable: number
    }
    todayAppointments: Array<{
        id: string
        time: string
        patient_id: string
        patient_name: string
        patient_record: string
        reason: string
        doctor_name: string
        room_name: string
        status: string
        duration_minutes: number
    }>
    financialChart: Array<{
        day: string
        date: string
        production: number
        collected: number
    }>
    alerts: {
        overdue_accounts_count: number
        low_stock_count: number
        pending_lab_orders_count: number
    }
    branches: Array<{
        id: string
        name: string
        is_main: boolean
    }>
    selectedBranchId?: string | null
    inventoryData?: {
        total_items: number
        low_stock_count: number
        expiring_batches_count: number
        critical_items: Array<{
            id: string
            name: string
            sku: string
            category: string
            current_stock: number
            min_stock: number
            unit: string
        }>
        expiring_batches: Array<{
            id: string
            item_name: string
            lot_number: string
            quantity: number
            expiry_date: string
            days_remaining: number
        }>
        recent_movements: Array<{
            id: string
            item_name: string
            type: string
            quantity: number
            previous_stock: number
            new_stock: number
            notes: string | null
            user_name: string
            created_at: string
        }>
    }
    cashierData?: {
        has_open_session: boolean
        active_session_name?: string | null
        opening_amount: number
        collected_today: number
        pending_charges_count: number
        recent_payments: Array<{
            id: string
            receipt_number: string
            patient_name: string
            total_amount: number
            methods: string
            time: string
        }>
        pending_charges: Array<{
            id: string
            charge_number: string
            patient_name: string
            patient_record: string
            concept: string
            total_amount: number
            balance_due: number
            status: string
        }>
    }
    dentistData?: {
        my_appointments_today: number
        patients_waiting: number
        patients_completed: number
        pending_lab_count: number
        my_appointments: Array<{
            id: string
            time: string
            patient_id: string
            patient_name: string
            patient_record: string
            reason: string
            room_name: string
            status: string
        }>
        my_lab_orders: Array<{
            id: string
            order_number: string
            patient_name: string
            lab_name: string
            work_type: string
            status: string
            due_date: string
        }>
    }
    receptionData?: {
        follow_ups_due: Array<{
            id: string
            patient_name: string
            patient_phone: string | null
            title: string
            channel: string
            due_date: string
        }>
        unconfirmed_count: number
    }
}

const props = defineProps<Props>()

const primaryRole = computed(() => props.user.primary_role)
const permissionSet = computed(() => new Set(props.user.permissions))
const isOwner = computed(() => props.user.roles.includes('Owner'))

function can(permission: string) {
    return isOwner.value || permissionSet.value.has(permission)
}

function formatMoney(amount: number) {
    return new Intl.NumberFormat('es-DO', {
        style: 'currency',
        currency: props.clinic.currency || 'USD',
        minimumFractionDigits: 2,
    }).format(amount || 0)
}

function onBranchChange(e: Event) {
    const target = e.target as HTMLSelectElement
    router.get('/dashboard', { branch_id: target.value }, { preserveState: true })
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'waiting':
            return { label: 'En espera', class: 'bg-[#EAEDFF] text-[#0047BF] border-[#D0E1FB]' }
        case 'confirmed':
            return { label: 'Confirmada', class: 'bg-[#A3FAEF]/30 text-[#005C55] border-[#80D5CB]' }
        case 'in_progress':
            return { label: 'En atención', class: 'bg-amber-100 text-amber-800 border-amber-300' }
        case 'completed':
            return { label: 'Completada', class: 'bg-emerald-100 text-emerald-800 border-emerald-300' }
        case 'cancelled':
            return { label: 'Cancelada', class: 'bg-rose-100 text-rose-800 border-rose-300' }
        default:
            return { label: 'Programada', class: 'bg-[#F2F3FF] text-[#505F76] border-[#E2E8F0]' }
    }
}

function getMovementBadge(type: string) {
    switch (type) {
        case 'purchase':
            return { label: 'Compra', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
        case 'consumption':
            return { label: 'Consumo', class: 'bg-blue-50 text-blue-700 border-blue-200' }
        case 'adjustment':
            return { label: 'Ajuste', class: 'bg-amber-50 text-amber-800 border-amber-200' }
        case 'waste':
            return { label: 'Merma', class: 'bg-rose-50 text-rose-700 border-rose-200' }
        default:
            return { label: type, class: 'bg-slate-100 text-slate-700 border-slate-200' }
    }
}

const currentDateFormatted = new Intl.DateTimeFormat('es-ES', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long', 
    year: 'numeric' 
}).format(new Date())
</script>

<template>
    <ClinicLayout>
        <Head :title="`${clinic.trade_name} — Panel de Control`" />

        <div class="space-y-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-2 border-b border-[#E2E8F0]">
                <div>
                    <div class="flex items-center gap-2">
                        <span 
                            class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border"
                            :class="{
                                'bg-teal-50 text-[#005C55] border-teal-200': primaryRole === 'Owner',
                                'bg-amber-50 text-amber-800 border-amber-200': primaryRole === 'InventoryManager',
                                'bg-emerald-50 text-emerald-800 border-emerald-200': primaryRole === 'Cashier',
                                'bg-blue-50 text-blue-700 border-blue-200': primaryRole === 'Dentist',
                                'bg-purple-50 text-purple-700 border-purple-200': primaryRole === 'Receptionist'
                            }"
                        >
                            {{ 
                                primaryRole === 'Owner' ? 'Mando Ejecutivo' :
                                primaryRole === 'InventoryManager' ? 'Gestión de Almacén & Stock' :
                                primaryRole === 'Cashier' ? 'Caja & Cobranzas' :
                                primaryRole === 'Dentist' ? 'Consultorio Clínico' : 'Recepción & Citas'
                            }}
                        </span>
                    </div>
                    <h2 class="font-display-md text-2xl font-bold text-[#131B2E] mt-1">
                        Hola, {{ user.name }}
                    </h2>
                    <p class="text-xs text-[#505F76] capitalize">
                        {{ clinic.trade_name }} • <span class="font-medium text-[#131B2E]">{{ currentDateFormatted }}</span>
                    </p>
                </div>

                <!-- Filters toolbar -->
                <div class="flex items-center gap-3">
                    <select 
                        v-if="branches.length > 1"
                        :value="selectedBranchId || ''"
                        class="bg-white border border-[#BDC9C6] text-[#131B2E] rounded-lg px-3 py-1.5 text-xs font-medium focus:border-[#005C55] focus:ring-1 focus:ring-[#005C55] transition"
                        @change="onBranchChange"
                    >
                        <option value="">Todas las Sedes</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>

                    <div class="px-3 py-1.5 bg-[#F2F3FF] text-[#005C55] border border-[#BDC9C6] rounded-lg text-xs font-bold">
                        En vivo
                    </div>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- 1. DASHBOARD DE ALMACÉN / INVENTARIO (InventoryManager)                   -->
            <!-- ========================================================================= -->
            <template v-if="primaryRole === 'InventoryManager' && inventoryData">
                <!-- 4 Inventory KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Insumos en Catálogo</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-[#005C55] flex items-center justify-center">
                                <Package class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ inventoryData.total_items }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Bajo Stock Mínimo</span>
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center">
                                <AlertTriangle class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-amber-700 font-data-tabular">{{ inventoryData.low_stock_count }}</span>
                            <span class="text-xs text-[#505F76]">artículos</span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Lotes por Vencer (&lt;60d)</span>
                            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center">
                                <Clock class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-rose-700 font-data-tabular">{{ inventoryData.expiring_batches_count }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Movimientos Recientes</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                                <Boxes class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ inventoryData.recent_movements.length }}</span>
                    </div>
                </div>

                <!-- 2-Column Inventory Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left 2 Cols: Critical items & Expiring Batches -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Critical Stock Table -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <div class="flex items-center gap-2">
                                    <AlertTriangle class="w-4 h-4 text-amber-600" />
                                    <h3 class="font-section-title text-[#131B2E]">Insumos con Stock Crítico (Requieren Compra)</h3>
                                </div>
                                <Link href="/inventory" class="text-xs font-bold text-[#005C55] hover:underline flex items-center gap-1">
                                    Ver Todo en Inventario <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>

                            <div class="overflow-x-auto">
                                <table v-if="inventoryData.critical_items.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Insumo</th>
                                            <th class="px-4 py-2.5 font-semibold">Categoría</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Stock Actual</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Mínimo</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr v-for="item in inventoryData.critical_items" :key="item.id" class="hover:bg-[#F8FAFC]">
                                            <td class="px-4 py-3 font-semibold text-[#131B2E]">
                                                {{ item.name }}
                                                <span class="block text-[10px] text-[#505F76] font-mono">{{ item.sku }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-[#505F76]">{{ item.category }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded border border-rose-200">
                                                    {{ item.current_stock }} {{ item.unit }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center font-mono text-[#505F76]">{{ item.min_stock }} {{ item.unit }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <Link href="/inventory" class="text-xs font-bold text-[#005C55] hover:underline">
                                                    Reponer
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                    🎉 Todos los insumos tienen stock por encima del umbral mínimo.
                                </div>
                            </div>
                        </div>

                        <!-- Expiring Batches Table -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <div class="flex items-center gap-2">
                                    <Clock class="w-4 h-4 text-rose-600" />
                                    <h3 class="font-section-title text-[#131B2E]">Lotes Próximos a Vencer</h3>
                                </div>
                                <span class="text-xs text-[#505F76]">Control FIFO</span>
                            </div>

                            <div class="overflow-x-auto">
                                <table v-if="inventoryData.expiring_batches.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Insumo</th>
                                            <th class="px-4 py-2.5 font-semibold">Lote #</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Cant. Restante</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Vencimiento</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr v-for="batch in inventoryData.expiring_batches" :key="batch.id" class="hover:bg-[#F8FAFC]">
                                            <td class="px-4 py-3 font-semibold text-[#131B2E]">{{ batch.item_name }}</td>
                                            <td class="px-4 py-3 font-mono font-bold text-[#505F76]">{{ batch.lot_number }}</td>
                                            <td class="px-4 py-3 text-center font-bold text-[#131B2E]">{{ batch.quantity }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-rose-50 text-rose-700 border-rose-200">
                                                    {{ batch.expiry_date }} ({{ batch.days_remaining }} días)
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                    No hay lotes con fecha de caducidad próxima en los siguientes 60 días.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Kardex Stream & Actions -->
                    <div class="space-y-6">
                        <!-- Quick Shortcuts Card -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4">Acciones de Almacén</h3>

                            <div class="grid grid-cols-2 gap-2.5">
                                <Link 
                                    href="/inventory" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Package class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Ver Catálogo</span>
                                </Link>

                                <Link 
                                    href="/inventory" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Boxes class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Kardex General</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Recent Kardex Movements Stream -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4 flex items-center justify-between">
                                <span>Movimientos Recientes</span>
                                <span class="text-[10px] text-[#505F76] font-mono">Tiempo Real</span>
                            </h3>

                            <div class="space-y-3">
                                <div 
                                    v-for="m in inventoryData.recent_movements" 
                                    :key="m.id"
                                    class="p-2.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg text-xs flex items-start justify-between gap-2"
                                >
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span :class="['px-1.5 py-0.5 rounded text-[10px] font-bold border', getMovementBadge(m.type).class]">
                                                {{ getMovementBadge(m.type).label }}
                                            </span>
                                            <span class="font-bold text-[#131B2E]">{{ m.item_name }}</span>
                                        </div>
                                        <p class="text-[11px] text-[#505F76] mt-1">{{ m.notes || `Registrado por ${m.user_name}` }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="font-bold font-mono text-[#131B2E] text-xs">
                                            {{ m.type === 'purchase' ? '+' : '−' }}{{ m.quantity }}
                                        </span>
                                        <span class="block text-[10px] text-[#505F76]">{{ m.created_at }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ========================================================================= -->
            <!-- 2. DASHBOARD DE CAJA Y COBRANZAS (Cashier)                                -->
            <!-- ========================================================================= -->
            <template v-else-if="primaryRole === 'Cashier' && cashierData">
                <!-- 4 Cashier KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Estado de Caja</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-[#005C55] flex items-center justify-center">
                                <CreditCard class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span 
                                :class="[
                                    'px-2 py-0.5 rounded text-xs font-bold border inline-block',
                                    cashierData.has_open_session ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'
                                ]"
                            >
                                {{ cashierData.has_open_session ? 'Caja Abierta' : 'Caja Cerrada' }}
                            </span>
                            <span v-if="cashierData.has_open_session" class="text-xs text-[#505F76] truncate">
                                {{ cashierData.active_session_name }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Cobrado Hoy en Caja</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                <DollarSign class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-emerald-700 font-data-tabular">{{ formatMoney(cashierData.collected_today) }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Cargos Pendientes</span>
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-800 flex items-center justify-center">
                                <ReceiptText class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-amber-800 font-data-tabular">{{ cashierData.pending_charges_count }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Fondo de Apertura</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                                <Wallet class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ formatMoney(cashierData.opening_amount) }}</span>
                    </div>
                </div>

                <!-- 2-Column Cashier Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Recent Payments Table -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <h3 class="font-section-title text-[#131B2E]">Cobros y Recibos del Día</h3>
                                <Link href="/cash-registers" class="text-xs font-bold text-[#005C55] hover:underline">
                                    Ver Arqueo
                                </Link>
                            </div>
                            <div class="overflow-x-auto">
                                <table v-if="cashierData.recent_payments.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Recibo #</th>
                                            <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                            <th class="px-4 py-2.5 font-semibold">Método</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Monto</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Hora</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr v-for="p in cashierData.recent_payments" :key="p.id" class="hover:bg-[#F8FAFC]">
                                            <td class="px-4 py-3 font-mono font-bold text-[#005C55]">{{ p.receipt_number }}</td>
                                            <td class="px-4 py-3 font-medium text-[#131B2E]">{{ p.patient_name }}</td>
                                            <td class="px-4 py-3 text-[#505F76]">{{ p.methods || 'Efectivo' }}</td>
                                            <td class="px-4 py-3 text-right font-bold text-[#131B2E] font-data-tabular">{{ formatMoney(p.total_amount) }}</td>
                                            <td class="px-4 py-3 text-center text-[#505F76]">{{ p.time }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                    No se han registrado cobros en la jornada actual.
                                </div>
                            </div>
                        </div>

                        <!-- Pending Patient Charges -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <h3 class="font-section-title text-[#131B2E]">Cargos Listos para Cobro</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table v-if="cashierData.pending_charges.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Cargo #</th>
                                            <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                            <th class="px-4 py-2.5 font-semibold">Concepto</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Saldo Pendiente</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr v-for="c in cashierData.pending_charges" :key="c.id" class="hover:bg-[#F8FAFC]">
                                            <td class="px-4 py-3 font-mono font-bold text-[#131B2E]">{{ c.charge_number }}</td>
                                            <td class="px-4 py-3 font-medium text-[#131B2E]">
                                                {{ c.patient_name }}
                                                <span class="block text-[10px] text-[#505F76] font-mono">{{ c.patient_record }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-[#505F76] truncate max-w-[200px]">{{ c.concept }}</td>
                                            <td class="px-4 py-3 text-right font-bold text-[#BA1A1A] font-data-tabular">{{ formatMoney(c.balance_due) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Cash Shortcuts -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4">Acciones de Caja</h3>
                            <div class="grid grid-cols-2 gap-2.5">
                                <Link 
                                    href="/cash-registers" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <CreditCard class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Gestionar Cajas</span>
                                </Link>
                                <Link 
                                    href="/cash-registers" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Wallet class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Arqueo de Sesión</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ========================================================================= -->
            <!-- 3. DASHBOARD CLÍNICO / ODONTÓLOGOS (Dentist)                              -->
            <!-- ========================================================================= -->
            <template v-else-if="primaryRole === 'Dentist' && dentistData">
                <!-- 4 Doctor KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Mis Citas de Hoy</span>
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-[#005C55] flex items-center justify-center">
                                <Calendar class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ dentistData.my_appointments_today }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">En Sala de Espera</span>
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center">
                                <Clock class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-blue-700 font-data-tabular">{{ dentistData.patients_waiting }}</span>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Atendidos Hoy</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                <CheckCircle2 class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-emerald-700 font-data-tabular">{{ dentistData.patients_completed }}</span>
                    </div>

                    <div v-if="can('lab.view')" class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Órdenes de Lab Activas</span>
                            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-700 flex items-center justify-center">
                                <FlaskConical class="w-4 h-4" />
                            </div>
                        </div>
                        <span class="text-2xl font-bold text-purple-700 font-data-tabular">{{ dentistData.pending_lab_count }}</span>
                    </div>
                </div>

                <!-- 2-Column Doctor Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- My Appointments Table -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <h3 class="font-section-title text-[#131B2E]">Mi Agenda Quirúrgica & Consultas</h3>
                                <Link href="/appointments" class="text-xs font-bold text-[#005C55] hover:underline">
                                    Ver Calendario
                                </Link>
                            </div>
                            <div class="overflow-x-auto">
                                <table v-if="dentistData.my_appointments.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Hora</th>
                                            <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                            <th class="px-4 py-2.5 font-semibold">Procedimiento / Motivo</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Estado</th>
                                            <th class="px-4 py-2.5 font-semibold text-right">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr v-for="a in dentistData.my_appointments" :key="a.id" class="hover:bg-[#F8FAFC]">
                                            <td class="px-4 py-3 font-mono font-bold text-[#131B2E]">{{ a.time }}</td>
                                            <td class="px-4 py-3 font-medium text-[#131B2E]">
                                                {{ a.patient_name }}
                                                <span class="block text-[10px] text-[#505F76] font-mono">{{ a.patient_record }}</span>
                                            </td>
                                            <td class="px-4 py-3 text-[#505F76]">{{ a.reason }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border', getStatusBadge(a.status).class]">
                                                    {{ getStatusBadge(a.status).label }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <Link :href="`/patients/${a.patient_id}`" class="text-xs font-bold text-[#005C55] hover:underline">
                                                    Ficha 360
                                                </Link>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                    No tienes consultas agendadas para el día de hoy.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Col: Lab Orders & Shortcuts -->
                    <div class="space-y-6">
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4">Accesos Clínicos</h3>
                            <div class="grid grid-cols-2 gap-2.5">
                                <Link 
                                    href="/appointments" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Calendar class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Mi Agenda</span>
                                </Link>
                                <Link 
                                    href="/patients" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Users class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Pacientes</span>
                                </Link>
                                <Link 
                                    href="/encounters" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <FileText class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Evolución SOAP</span>
                                </Link>
                                <Link 
                                    v-if="can('lab.view')"
                                    href="/lab" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <FlaskConical class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Órdenes Lab</span>
                                </Link>
                            </div>
                        </div>

                        <!-- Lab Orders -->
                        <div v-if="can('lab.view')" class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4">Órdenes de Prótesis Pendientes</h3>
                            <div v-if="dentistData.my_lab_orders.length > 0" class="space-y-3">
                                <div v-for="l in dentistData.my_lab_orders" :key="l.id" class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] rounded-lg text-xs">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-[#131B2E]">{{ l.patient_name }}</span>
                                        <span class="font-mono text-[10px] text-[#005C55] font-bold">{{ l.order_number }}</span>
                                    </div>
                                    <p class="text-[11px] text-[#505F76] mt-1">{{ l.work_type }} • {{ l.lab_name }}</p>
                                </div>
                            </div>
                            <div v-else class="text-xs text-[#505F76] text-center py-4">
                                No tienes órdenes de laboratorio en curso.
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ========================================================================= -->
            <!-- 4. DASHBOARD GENERAL / EJECUTIVO (Owner / ClinicDirector / Receptionist) -->
            <!-- ========================================================================= -->
            <template v-else-if="primaryRole === 'Owner' || primaryRole === 'Receptionist'">
                <!-- 4 Top KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Citas hoy</span>
                            <div class="w-8 h-8 rounded-lg bg-[#F2F3FF] text-[#005C55] flex items-center justify-center">
                                <Calendar class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ kpis.appointments_today }}</span>
                            <span class="flex items-center text-[#005C55] text-xs font-semibold">
                                <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.appointments_trend }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Pacientes atendidos</span>
                            <div class="w-8 h-8 rounded-lg bg-[#F2F3FF] text-[#005C55] flex items-center justify-center">
                                <UserCheck class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ kpis.patients_attended_today }}</span>
                            <span class="flex items-center text-[#0047BF] text-xs font-semibold">
                                <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.attended_trend }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Cobrado neto hoy</span>
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center">
                                <CreditCard class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#131B2E] font-data-tabular">{{ formatMoney(kpis.net_collected_today) }}</span>
                            <span class="flex items-center text-emerald-700 text-xs font-semibold">
                                <TrendingUp class="w-3.5 h-3.5 mr-0.5" /> {{ kpis.collected_trend }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl p-4 border border-[#E2E8F0] shadow-xs flex flex-col justify-between">
                        <div class="flex justify-between items-start mb-2">
                            <span class="text-xs font-semibold text-[#505F76]">Cuentas por cobrar</span>
                            <div class="w-8 h-8 rounded-lg bg-[#FFDAD6]/40 text-[#BA1A1A] flex items-center justify-center">
                                <Wallet class="w-4 h-4" />
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl font-bold text-[#BA1A1A] font-data-tabular">{{ formatMoney(kpis.accounts_receivable) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Main 2-Column Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Agenda de Hoy Card -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs overflow-hidden">
                            <div class="p-4 border-b border-[#E2E8F0] flex justify-between items-center bg-[#FAF8FF]">
                                <div class="flex items-center gap-2">
                                    <Clock class="w-4 h-4 text-[#005C55]" />
                                    <h3 class="font-section-title text-[#131B2E]">Agenda de hoy</h3>
                                </div>
                                <Link href="/appointments" class="text-xs font-bold text-[#005C55] hover:underline flex items-center gap-1">
                                    Ver todo <ArrowRight class="w-3.5 h-3.5" />
                                </Link>
                            </div>

                            <div class="overflow-x-auto">
                                <table v-if="todayAppointments.length > 0" class="w-full text-left border-collapse">
                                    <thead class="bg-[#F8FAFC] font-label-caps text-[#505F76] border-b border-[#E2E8F0]">
                                        <tr>
                                            <th class="px-4 py-2.5 font-semibold">Hora</th>
                                            <th class="px-4 py-2.5 font-semibold">Paciente</th>
                                            <th class="px-4 py-2.5 font-semibold">Motivo</th>
                                            <th class="px-4 py-2.5 font-semibold">Doctor</th>
                                            <th class="px-4 py-2.5 font-semibold text-center">Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-xs divide-y divide-[#E2E8F0]">
                                        <tr 
                                            v-for="app in todayAppointments"
                                            :key="app.id"
                                            class="hover:bg-[#F8FAFC] transition-colors h-11"
                                        >
                                            <td class="px-4 py-2 font-data-tabular font-bold text-[#131B2E]">{{ app.time }}</td>
                                            <td class="px-4 py-2 font-medium text-[#131B2E]">
                                                <Link :href="`/patients/${app.patient_id}`" class="hover:text-[#005C55] hover:underline">
                                                    {{ app.patient_name }}
                                                </Link>
                                            </td>
                                            <td class="px-4 py-2 text-[#505F76] truncate max-w-[180px]">{{ app.reason }}</td>
                                            <td class="px-4 py-2 text-[#505F76]">{{ app.doctor_name }}</td>
                                            <td class="px-4 py-2 text-center">
                                                <span 
                                                    :class="[
                                                        'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border',
                                                        getStatusBadge(app.status).class
                                                    ]"
                                                >
                                                    {{ getStatusBadge(app.status).label }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-else class="p-8 text-center text-xs text-[#505F76]">
                                    No hay citas programadas para el día de hoy.
                                </div>
                            </div>
                        </div>

                        <!-- Financial Production vs Collections Chart (Owner Only) -->
                        <div v-if="financialChart.length > 0" class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="font-section-title text-[#131B2E]">Finanzas — Producción vs Cobrado</h3>
                                    <p class="text-xs text-[#505F76] mt-0.5">Últimos 7 días de actividad clínica</p>
                                </div>
                                <div class="flex items-center gap-4 text-xs font-semibold">
                                    <span class="flex items-center gap-1.5 text-[#005C55]">
                                        <span class="w-2.5 h-2.5 rounded-full bg-[#005C55]"></span> Producción
                                    </span>
                                    <span class="flex items-center gap-1.5 text-emerald-700">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Cobrado
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-7 gap-3 pt-4 border-t border-[#E2E8F0]">
                                <div 
                                    v-for="d in financialChart"
                                    :key="d.date"
                                    class="flex flex-col items-center gap-2"
                                >
                                    <div class="w-full h-36 bg-[#F8FAFC] rounded-lg flex items-end justify-center gap-1.5 p-1.5 border border-[#E2E8F0]">
                                        <div 
                                            class="w-3 bg-[#005C55] rounded-t transition-all duration-300"
                                            :style="{ height: `${Math.min(100, Math.max(10, d.production / 10))}%` }"
                                            :title="`Producción: ${formatMoney(d.production)}`"
                                        ></div>
                                        <div 
                                            class="w-3 bg-emerald-500 rounded-t transition-all duration-300"
                                            :style="{ height: `${Math.min(100, Math.max(10, d.collected / 10))}%` }"
                                            :title="`Cobrado: ${formatMoney(d.collected)}`"
                                        ></div>
                                    </div>
                                    <span class="font-label-caps text-[10px] text-[#505F76]">{{ d.day }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right 1-Col: Alerts & Quick Shortcuts -->
                    <div class="space-y-6">
                        <!-- Operational Alerts -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4 flex items-center gap-2">
                                <AlertTriangle class="w-4 h-4 text-[#BA1A1A]" />
                                <span>Requieren atención</span>
                            </h3>

                            <div class="space-y-3">
                                <div v-if="can('payments.view') || can('finance.view')" class="flex items-start gap-3 p-3 rounded-lg bg-[#FFDAD6]/30 border border-[#BA1A1A]/30">
                                    <Wallet class="w-4 h-4 text-[#BA1A1A] shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-[#131B2E]">{{ alerts.overdue_accounts_count }} cuentas por cobrar</p>
                                        <p class="text-[11px] text-[#505F76] mt-0.5">Saldos pendientes de cobro a pacientes.</p>
                                    </div>
                                    <Link href="/patients" class="text-xs font-bold text-[#BA1A1A] hover:underline shrink-0">
                                        Revisar
                                    </Link>
                                </div>

                                <div v-if="can('inventory.view')" class="flex items-start gap-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                                    <Package class="w-4 h-4 text-amber-700 shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-[#131B2E]">{{ alerts.low_stock_count }} insumos bajo mínimo</p>
                                        <p class="text-[11px] text-[#505F76] mt-0.5">Alerta de reposición en almacén.</p>
                                    </div>
                                    <Link href="/inventory" class="text-xs font-bold text-amber-800 hover:underline shrink-0">
                                        Kardex
                                    </Link>
                                </div>

                                <div v-if="can('lab.view')" class="flex items-start gap-3 p-3 rounded-lg bg-[#F2F3FF] border border-[#D0E1FB]">
                                    <FlaskConical class="w-4 h-4 text-[#0047BF] shrink-0 mt-0.5" />
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-[#131B2E]">{{ alerts.pending_lab_orders_count }} órdenes de prótesis</p>
                                        <p class="text-[11px] text-[#505F76] mt-0.5">Trabajos en proceso con laboratorio.</p>
                                    </div>
                                    <Link href="/lab" class="text-xs font-bold text-[#0047BF] hover:underline shrink-0">
                                        Órdenes
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Shortcuts Card -->
                        <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-5">
                            <h3 class="font-section-title text-[#131B2E] mb-4">Accesos Rápidos</h3>

                            <div class="grid grid-cols-2 gap-2.5">
                                <Link 
                                    v-if="can('appointments.create')"
                                    href="/appointments" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Calendar class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Nueva Cita</span>
                                </Link>

                                <Link 
                                    v-if="can('patients.create')"
                                    href="/patients/create" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <Users class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Nuevo Paciente</span>
                                </Link>

                                <Link 
                                    v-if="can('cash.open')"
                                    href="/cash-registers" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <CreditCard class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Abrir Caja</span>
                                </Link>

                                <Link 
                                    v-if="can('finance.reports')"
                                    href="/analytics" 
                                    class="p-3 bg-[#F8FAFC] border border-[#E2E8F0] hover:border-[#005C55] rounded-xl text-center flex flex-col items-center gap-1.5 transition group"
                                >
                                    <TrendingUp class="w-5 h-5 text-[#005C55] group-hover:scale-110 transition-transform" />
                                    <span class="text-xs font-bold text-[#131B2E]">Analítica KPI</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <template v-else>
                <div class="bg-white rounded-xl border border-[#E2E8F0] shadow-xs p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-lg bg-[#F2F3FF] text-[#005C55] flex items-center justify-center shrink-0">
                            <ShieldAlert class="w-5 h-5" />
                        </div>
                        <div class="space-y-3">
                            <div>
                                <h3 class="font-section-title text-[#131B2E]">Panel operativo</h3>
                                <p class="text-xs text-[#505F76] mt-1">Tu acceso esta limitado a los modulos autorizados para tu rol.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Link v-if="can('lab.view')" href="/lab" class="px-3 py-2 rounded-lg bg-[#F2F3FF] text-[#005C55] text-xs font-bold">
                                    <FlaskConical class="w-4 h-4 inline mr-1" />
                                    Laboratorio
                                </Link>
                                <Link v-if="can('inventory.view')" href="/inventory" class="px-3 py-2 rounded-lg bg-amber-50 text-amber-800 text-xs font-bold">
                                    <Package class="w-4 h-4 inline mr-1" />
                                    Inventario
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </ClinicLayout>
</template>
