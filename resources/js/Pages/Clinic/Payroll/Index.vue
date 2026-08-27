<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { appUrl } from '@/lib/url'
import ClinicLayout from '@/Layouts/ClinicLayout.vue'
import { Banknote, CalendarDays, CheckCircle2, Landmark, Pencil, Plus, Stethoscope, UserRound, UsersRound } from 'lucide-vue-next'
import { ref } from 'vue'

interface Professional { id: string; full_name: string }
interface Employee {
    id: string; employee_number: string; professional_id: string | null; full_name: string; position: string | null
    compensation_type: 'fixed_salary' | 'commission'; monthly_salary: number; commission_rate: number
    hire_date: string | null; status: 'active' | 'inactive'; professional?: Professional | null
}
interface PayrollLine { id: string; type: 'fixed_salary' | 'commission'; description: string; amount: number }
interface PayrollItem { id: string; fixed_salary_amount: number; commission_amount: number; net_amount: number; status: string; employee: Employee; lines: PayrollLine[] }
interface PayrollRun {
    id: string; run_number: string; period_start: string; period_end: string; status: 'draft' | 'paid'
    fixed_salary_total: number; commission_total: number; net_total: number; paid_at: string | null; items: PayrollItem[]
}

const props = defineProps<{
    employees: Employee[]
    professionals: Professional[]
    runs: PayrollRun[]
    summary: { active_employees: number; accrued_commissions: number; draft_payroll: number }
}>()

const editingEmployee = ref<Employee | null>(null)
const employeeForm = useForm({
    full_name: '', position: '', compensation_type: 'fixed_salary' as Employee['compensation_type'],
    professional_id: '', monthly_salary: null as number | null, commission_rate: null as number | null,
    hire_date: '', status: 'active' as Employee['status'],
})
const runForm = useForm({ month: new Date().toISOString().slice(0, 7) })
const payForm = useForm({})

const money = (value: number) => new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP' }).format(Number(value || 0))
const date = (value: string) => new Intl.DateTimeFormat('es-DO', { dateStyle: 'medium' }).format(new Date(`${value.slice(0, 10)}T12:00:00`))

function editEmployee(employee: Employee) {
    editingEmployee.value = employee
    employeeForm.clearErrors()
    employeeForm.full_name = employee.full_name
    employeeForm.position = employee.position || ''
    employeeForm.compensation_type = employee.compensation_type
    employeeForm.professional_id = employee.professional_id || ''
    employeeForm.monthly_salary = employee.monthly_salary
    employeeForm.commission_rate = employee.commission_rate
    employeeForm.hire_date = employee.hire_date?.slice(0, 10) || ''
    employeeForm.status = employee.status
}

function resetEmployeeForm() {
    editingEmployee.value = null
    employeeForm.reset()
    employeeForm.clearErrors()
}

function submitEmployee() {
    const options = { preserveScroll: true, onSuccess: resetEmployeeForm }
    if (editingEmployee.value) employeeForm.put(appUrl(`/payroll/employees/${editingEmployee.value.id}`), options)
    else employeeForm.post(appUrl('/payroll/employees'), options)
}

function createRun() {
    runForm.post(appUrl('/payroll/runs'), { preserveScroll: true })
}

function payRun(run: PayrollRun) {
    if (window.confirm(`¿Confirmar el pago de ${money(run.net_total)} para ${run.run_number}?`)) {
        payForm.post(appUrl(`/payroll/runs/${run.id}/pay`), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Nómina — BSDental" />
    <ClinicLayout>
        <div class="space-y-5">
            <header class="flex flex-col justify-between gap-3 border-b border-[#D8E0DE] pb-5 lg:flex-row lg:items-end">
                <div><p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#006B63]">Finanzas y personal</p><h1 class="mt-1 flex items-center gap-2 text-2xl font-bold"><Landmark class="h-6 w-6 text-[#005C55]" /> Nómina</h1><p class="mt-1 text-sm text-[#667085]">Sueldos fijos y comisiones médicas basadas en procedimientos realizados.</p></div>
                <form class="flex flex-wrap items-end gap-2" @submit.prevent="createRun"><label class="text-xs font-bold text-[#455653]">Período mensual<input v-model="runForm.month" type="month" required class="mt-1 block h-10 border border-[#9AAEAA] bg-white px-3 text-sm" /></label><button :disabled="runForm.processing" class="inline-flex h-10 items-center gap-2 rounded-md bg-[#005C55] px-4 text-sm font-bold text-white disabled:opacity-50"><CalendarDays class="h-4 w-4" /> Calcular nómina</button><p v-if="runForm.errors.month" class="w-full text-xs text-[#B42318]">{{ runForm.errors.month }}</p></form>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="flex items-center gap-2 text-xs font-bold uppercase text-[#667085]"><UsersRound class="h-4 w-4" /> Personal activo</p><p class="mt-2 font-mono text-2xl font-bold">{{ summary.active_employees }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="flex items-center gap-2 text-xs font-bold uppercase text-[#667085]"><Stethoscope class="h-4 w-4" /> Comisiones por liquidar</p><p class="mt-2 font-mono text-2xl font-bold text-[#006B63]">{{ money(summary.accrued_commissions) }}</p></article>
                <article class="border border-[#D8E0DE] bg-white p-4"><p class="flex items-center gap-2 text-xs font-bold uppercase text-[#667085]"><Banknote class="h-4 w-4" /> Nómina pendiente</p><p class="mt-2 font-mono text-2xl font-bold text-[#B54708]">{{ money(summary.draft_payroll) }}</p></article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[380px_1fr]">
                <form class="h-fit border border-[#BDC9C6] bg-white" @submit.prevent="submitEmployee">
                    <header class="border-b border-[#D8E0DE] bg-[#F7FAF9] p-4"><h2 class="font-bold">{{ editingEmployee ? 'Editar configuración salarial' : 'Agregar empleado' }}</h2><p class="mt-1 text-xs text-[#667085]">Vincula doctores para comisión o registra personal de sueldo fijo.</p></header>
                    <div class="grid gap-3 p-4">
                        <label class="text-xs font-bold text-[#455653]">Tipo de remuneración<select v-model="employeeForm.compensation_type" class="mt-1 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm"><option value="fixed_salary">Sueldo fijo mensual</option><option value="commission">Doctor por comisión</option></select></label>
                        <label v-if="employeeForm.compensation_type === 'commission'" class="text-xs font-bold text-[#455653]">Profesional<select v-model="employeeForm.professional_id" required class="mt-1 h-10 w-full border border-[#9AAEAA] bg-white px-3 text-sm"><option value="">Seleccionar doctor</option><option v-for="professional in professionals" :key="professional.id" :value="professional.id">{{ professional.full_name }}</option></select></label>
                        <label class="text-xs font-bold text-[#455653]">Nombre completo<input v-model="employeeForm.full_name" required class="mt-1 h-10 w-full border border-[#9AAEAA] px-3 text-sm" /></label>
                        <label class="text-xs font-bold text-[#455653]">Cargo<input v-model="employeeForm.position" placeholder="Ej. Recepcionista, odontólogo" class="mt-1 h-10 w-full border border-[#9AAEAA] px-3 text-sm" /></label>
                        <label v-if="employeeForm.compensation_type === 'fixed_salary'" class="text-xs font-bold text-[#455653]">Sueldo mensual (DOP)<input v-model.number="employeeForm.monthly_salary" type="number" min="0" step="0.01" required class="mt-1 h-10 w-full border border-[#9AAEAA] px-3 font-mono text-sm" /></label>
                        <label v-else class="text-xs font-bold text-[#455653]">Comisión sobre producción (%)<input v-model.number="employeeForm.commission_rate" type="number" min="0" max="100" step="0.01" required class="mt-1 h-10 w-full border border-[#9AAEAA] px-3 font-mono text-sm" /></label>
                        <div class="grid grid-cols-2 gap-2"><label class="text-xs font-bold text-[#455653]">Fecha de ingreso<input v-model="employeeForm.hire_date" type="date" class="mt-1 h-10 w-full border border-[#9AAEAA] px-2 text-sm" /></label><label v-if="editingEmployee" class="text-xs font-bold text-[#455653]">Estado<select v-model="employeeForm.status" class="mt-1 h-10 w-full border border-[#9AAEAA] bg-white px-2 text-sm"><option value="active">Activo</option><option value="inactive">Inactivo</option></select></label></div>
                        <p v-if="Object.keys(employeeForm.errors).length" class="text-xs text-[#B42318]">{{ Object.values(employeeForm.errors)[0] }}</p>
                        <div class="flex gap-2"><button :disabled="employeeForm.processing" class="inline-flex h-10 flex-1 items-center justify-center gap-2 bg-[#005C55] px-4 text-sm font-bold text-white"><Plus v-if="!editingEmployee" class="h-4 w-4" /><Pencil v-else class="h-4 w-4" />{{ editingEmployee ? 'Guardar cambios' : 'Agregar a nómina' }}</button><button v-if="editingEmployee" type="button" class="h-10 border border-[#9AAEAA] px-3 text-sm" @click="resetEmployeeForm">Cancelar</button></div>
                    </div>
                </form>

                <div class="border border-[#BDC9C6] bg-white">
                    <header class="border-b border-[#D8E0DE] bg-[#F7FAF9] p-4"><h2 class="font-bold">Personal y condiciones vigentes</h2></header>
                    <div v-if="employees.length" class="divide-y divide-[#E2E8F0]">
                        <article v-for="employee in employees" :key="employee.id" class="grid gap-3 p-4 md:grid-cols-[1fr_180px_170px_auto] md:items-center"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#E7F0FF] font-bold text-[#455A73]"><Stethoscope v-if="employee.compensation_type === 'commission'" class="h-5 w-5" /><UserRound v-else class="h-5 w-5" /></span><div><p class="font-bold">{{ employee.full_name }}</p><p class="text-xs text-[#667085]">{{ employee.employee_number }} · {{ employee.position || 'Sin cargo' }}</p></div></div><div><p class="text-[10px] font-bold uppercase text-[#667085]">Esquema</p><p class="mt-1 text-sm font-semibold">{{ employee.compensation_type === 'commission' ? 'Comisión por producción' : 'Sueldo fijo' }}</p></div><div class="font-mono font-bold text-[#006B63]">{{ employee.compensation_type === 'commission' ? `${employee.commission_rate}%` : money(employee.monthly_salary) }}</div><button type="button" class="inline-flex h-9 items-center gap-1 border border-[#9AAEAA] px-3 text-xs font-bold" @click="editEmployee(employee)"><Pencil class="h-3.5 w-3.5" /> Editar</button></article>
                    </div>
                    <div v-else class="p-10 text-center text-sm text-[#667085]">Aún no hay empleados configurados para nómina.</div>
                </div>
            </section>

            <section class="border border-[#BDC9C6] bg-white">
                <header class="border-b border-[#D8E0DE] bg-[#F7FAF9] p-4"><h2 class="font-bold">Períodos calculados</h2><p class="mt-1 text-xs text-[#667085]">Cada cálculo congela el sueldo y las comisiones incluidas para evitar pagos duplicados.</p></header>
                <div v-if="runs.length" class="divide-y divide-[#D8E0DE]">
                    <details v-for="run in runs" :key="run.id" class="group" :open="run === runs[0]"><summary class="grid cursor-pointer list-none gap-3 p-4 md:grid-cols-[1fr_170px_170px_130px] md:items-center"><div><p class="font-mono text-xs font-bold text-[#006B63]">{{ run.run_number }}</p><p class="mt-1 text-sm font-semibold">{{ date(run.period_start) }} – {{ date(run.period_end) }}</p></div><div><p class="text-[10px] font-bold uppercase text-[#667085]">Sueldos / Comisiones</p><p class="mt-1 font-mono text-sm">{{ money(run.fixed_salary_total) }} / {{ money(run.commission_total) }}</p></div><p class="font-mono text-lg font-bold">{{ money(run.net_total) }}</p><span class="w-fit px-2 py-1 text-xs font-bold" :class="run.status === 'paid' ? 'bg-[#D8ECE9] text-[#006B63]' : 'bg-[#FFF4E5] text-[#B54708]'">{{ run.status === 'paid' ? 'Pagada' : 'Borrador' }}</span></summary>
                        <div class="border-t border-[#E2E8F0] bg-[#F8FAFC] p-4"><div class="overflow-x-auto"><table class="w-full min-w-[700px] text-left text-sm"><thead class="text-[10px] uppercase text-[#667085]"><tr><th class="pb-2">Empleado</th><th class="pb-2 text-right">Sueldo</th><th class="pb-2 text-right">Comisión</th><th class="pb-2 text-right">Total</th></tr></thead><tbody class="divide-y divide-[#D8E0DE]"><tr v-for="item in run.items" :key="item.id"><td class="py-3"><p class="font-semibold">{{ item.employee.full_name }}</p><p class="text-xs text-[#667085]">{{ item.lines.map(line => line.description).join(' · ') || 'Sin conceptos' }}</p></td><td class="py-3 text-right font-mono">{{ money(item.fixed_salary_amount) }}</td><td class="py-3 text-right font-mono">{{ money(item.commission_amount) }}</td><td class="py-3 text-right font-mono font-bold">{{ money(item.net_amount) }}</td></tr></tbody></table></div><div v-if="run.status === 'draft'" class="mt-4 flex justify-end"><button :disabled="payForm.processing" class="inline-flex h-10 items-center gap-2 bg-[#005C55] px-4 text-sm font-bold text-white" @click="payRun(run)"><CheckCircle2 class="h-4 w-4" /> Confirmar pago de nómina</button></div></div>
                    </details>
                </div>
                <div v-else class="p-10 text-center text-sm text-[#667085]">No hay períodos calculados.</div>
            </section>
        </div>
    </ClinicLayout>
</template>
