<?php

namespace App\Core\Controllers;

use App\Core\Models\Employee;
use App\Core\Models\PayrollRun;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Services\PayrollService;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService,
        protected AuditLogger $auditLogger,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Clinic/Payroll/Index', [
            'employees' => Employee::with('professional')->orderBy('full_name')->get(),
            'professionals' => Professional::where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get(),
            'runs' => PayrollRun::with(['items.employee', 'items.lines'])->latest('period_start')->get(),
            'summary' => [
                'active_employees' => Employee::where('status', 'active')->count(),
                'accrued_commissions' => round((float) ProfessionalCompensation::where('status', 'accrued')->sum('commission_amount'), 2),
                'draft_payroll' => round((float) PayrollRun::where('status', 'draft')->sum('net_total'), 2),
            ],
        ]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $validated = $this->validateEmployee($request);
        $employee = Employee::create([
            ...$validated,
            'employee_number' => 'EMP-'.Str::upper(Str::random(8)),
            'professional_id' => $validated['compensation_type'] === 'commission' ? $validated['professional_id'] : null,
            'monthly_salary' => $validated['compensation_type'] === 'fixed_salary' ? $validated['monthly_salary'] : 0,
            'commission_rate' => $validated['compensation_type'] === 'commission' ? $validated['commission_rate'] : 0,
            'status' => 'active',
        ]);

        $this->auditLogger->logTenant('employee.created', 'Employee', $employee->id, [
            'employee_number' => $employee->employee_number,
            'compensation_type' => $employee->compensation_type,
        ]);

        return back()->with('success', 'Empleado agregado a nómina.');
    }

    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validateEmployee($request, $employee);
        $employee->update([
            ...$validated,
            'professional_id' => $validated['compensation_type'] === 'commission' ? $validated['professional_id'] : null,
            'monthly_salary' => $validated['compensation_type'] === 'fixed_salary' ? $validated['monthly_salary'] : 0,
            'commission_rate' => $validated['compensation_type'] === 'commission' ? $validated['commission_rate'] : 0,
        ]);

        $this->auditLogger->logTenant('employee.updated', 'Employee', $employee->id, [
            'compensation_type' => $employee->compensation_type,
            'status' => $employee->status,
        ]);

        return back()->with('success', 'Configuración salarial actualizada.');
    }

    public function storeRun(Request $request): RedirectResponse
    {
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        try {
            $run = $this->payrollService->generateMonthlyRun(
                Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth(),
                (string) Auth::guard('web')->id(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['month' => $exception->getMessage()]);
        }

        $this->auditLogger->logTenant('payroll.run_created', 'PayrollRun', $run->id, [
            'run_number' => $run->run_number,
            'net_total' => $run->net_total,
        ]);

        return back()->with('success', 'Nómina mensual calculada.');
    }

    public function payRun(PayrollRun $run): RedirectResponse
    {
        try {
            $paid = $this->payrollService->markPaid($run, (string) Auth::guard('web')->id());
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['payroll' => $exception->getMessage()]);
        }

        $this->auditLogger->logTenant('payroll.run_paid', 'PayrollRun', $paid->id, [
            'run_number' => $paid->run_number,
            'net_total' => $paid->net_total,
        ]);

        return back()->with('success', 'Nómina marcada como pagada.');
    }

    /** @return array<string, mixed> */
    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'compensation_type' => ['required', Rule::in(['fixed_salary', 'commission'])],
            'professional_id' => [
                'nullable',
                'required_if:compensation_type,commission',
                'uuid',
                'exists:tenant.professionals,id',
                Rule::unique('tenant.employees', 'professional_id')->ignore($employee?->id),
            ],
            'monthly_salary' => ['nullable', 'required_if:compensation_type,fixed_salary', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'required_if:compensation_type,commission', 'numeric', 'min:0', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }
}
