<?php

namespace App\Core\Controllers;

use App\Core\Models\Appointment;
use App\Core\Models\AppointmentType;
use App\Core\Models\Branch;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Models\ScheduleBlock;
use App\Core\Services\AppointmentConflictValidator;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AppointmentController extends Controller
{
    public function __construct(
        protected AppointmentConflictValidator $conflictValidator,
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Display the clinic agenda.
     */
    public function index(Request $request): Response
    {
        $branchId = $request->input('branch_id');
        $professionalId = $request->input('professional_id');
        $date = $request->input('date', now()->toDateString());
        $view = in_array($request->input('view'), ['day', 'week', 'month'], true)
            ? $request->input('view')
            : 'week';
        $selectedDate = Carbon::parse($date);
        [$rangeStart, $rangeEnd] = match ($view) {
            'day' => [$selectedDate->copy()->startOfDay(), $selectedDate->copy()->endOfDay()],
            'month' => [$selectedDate->copy()->startOfMonth()->startOfWeek(), $selectedDate->copy()->endOfMonth()->endOfWeek()],
            default => [$selectedDate->copy()->startOfWeek(), $selectedDate->copy()->endOfWeek()],
        };

        $branches = Branch::where('is_active', true)->with('rooms')->get();
        $professionals = Professional::where('is_active', true)->with('specialties')->get();
        $appointmentTypes = AppointmentType::where('is_active', true)->get();

        $firstBranch = $branches->first();
        $selectedBranchId = $branchId ?: ($firstBranch ? $firstBranch->id : '');

        // Fetch appointments for the active branch/professional filter
        $appointments = Appointment::with(['patient', 'professional', 'room', 'appointmentType'])
            ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->when($professionalId, fn ($q) => $q->where('professional_id', $professionalId))
            ->whereBetween('start_time', [$rangeStart, $rangeEnd])
            ->orderBy('start_time')
            ->get();

        $blocks = ScheduleBlock::when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->whereBetween('start_time', [$rangeStart, $rangeEnd])
            ->get();

        return Inertia::render('Clinic/Appointments/Index', [
            'branches' => $branches,
            'professionals' => $professionals,
            'appointmentTypes' => $appointmentTypes,
            'patients' => Patient::query()
                ->where('status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(250)
                ->get(['id', 'record_number', 'first_name', 'last_name', 'phone']),
            'appointments' => $appointments,
            'blocks' => $blocks,
            'filters' => [
                'branch_id' => $selectedBranchId,
                'professional_id' => $professionalId,
                'date' => $date,
                'view' => $view,
            ],
        ]);
    }

    /**
     * Store a newly created appointment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid', 'exists:tenant.patients,id'],
            'professional_id' => ['required', 'uuid', 'exists:tenant.professionals,id'],
            'branch_id' => ['required', 'uuid', 'exists:tenant.branches,id'],
            'room_id' => ['nullable', 'uuid', 'exists:tenant.rooms,id'],
            'appointment_type_id' => ['nullable', 'uuid', 'exists:tenant.appointment_types,id'],
            'start_time' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = $startTime->copy()->addMinutes((int) $validated['duration_minutes']);

        // Prevent conflicts with pessimistic lock in transaction
        try {
            /** @var Appointment $appointment */
            $appointment = DB::connection('tenant')->transaction(function () use ($validated, $startTime, $endTime) {
                $this->conflictValidator->validate(
                    $validated['professional_id'],
                    $validated['branch_id'],
                    $startTime,
                    $endTime,
                    $validated['room_id'] ?? null
                );

                return Appointment::create([
                    'patient_id' => $validated['patient_id'],
                    'professional_id' => $validated['professional_id'],
                    'branch_id' => $validated['branch_id'],
                    'room_id' => $validated['room_id'] ?? null,
                    'appointment_type_id' => $validated['appointment_type_id'] ?? null,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $validated['duration_minutes'],
                    'status' => 'scheduled',
                    'reason' => $validated['reason'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['start_time' => $e->getMessage()]);
        }

        $this->auditLogger->logTenant('appointment.created', 'Appointment', $appointment->id, [
            'patient_id' => $appointment->patient_id,
            'professional_id' => $appointment->professional_id,
            'start_time' => $appointment->start_time->toIso8601String(),
        ]);

        return redirect()->back()->with('success', 'Cita agendada exitosamente.');
    }

    /**
     * Update appointment status (Reception / Clinical flow).
     */
    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:scheduled,confirmed,checked_in,waiting,in_progress,completed,cancelled,no_show'],
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $validated['status'];
        $now = now();

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'checked_in' && ! $appointment->checked_in_at) {
            $updateData['checked_in_at'] = $now;
        } elseif ($newStatus === 'in_progress' && ! $appointment->in_progress_at) {
            $updateData['in_progress_at'] = $now;
        } elseif ($newStatus === 'completed' && ! $appointment->completed_at) {
            $updateData['completed_at'] = $now;
        } elseif ($newStatus === 'cancelled') {
            $updateData['cancelled_at'] = $now;
            $updateData['cancellation_reason'] = $validated['cancellation_reason'] ?? null;
        }

        $appointment->update($updateData);

        $this->auditLogger->logTenant('appointment.status_updated', 'Appointment', $appointment->id, [
            'old_status' => $appointment->getOriginal('status'),
            'new_status' => $newStatus,
        ]);

        return redirect()->back()->with('success', "Estado de cita actualizado a {$newStatus}.");
    }

    /**
     * Reschedule appointment preserving clinical history.
     */
    public function reschedule(Request $request, string $id): RedirectResponse
    {
        $original = Appointment::findOrFail($id);

        $validated = $request->validate([
            'start_time' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'professional_id' => ['nullable', 'uuid', 'exists:tenant.professionals,id'],
            'room_id' => ['nullable', 'uuid', 'exists:tenant.rooms,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $startTime = Carbon::parse($validated['start_time']);
        $endTime = $startTime->copy()->addMinutes((int) $validated['duration_minutes']);
        $professionalId = $validated['professional_id'] ?? $original->professional_id;
        $roomId = $validated['room_id'] ?? $original->room_id;

        try {
            /** @var Appointment $rescheduled */
            $rescheduled = DB::connection('tenant')->transaction(function () use ($original, $validated, $professionalId, $roomId, $startTime, $endTime) {
                // Validate conflict for the new slot
                $this->conflictValidator->validate(
                    $professionalId,
                    $original->branch_id,
                    $startTime,
                    $endTime,
                    $roomId
                );

                // Mark original as rescheduled
                $original->update([
                    'status' => 'rescheduled',
                    'cancellation_reason' => 'Reprogramada para nueva fecha.',
                ]);

                // Create new linked appointment
                return Appointment::create([
                    'patient_id' => $original->patient_id,
                    'professional_id' => $professionalId,
                    'branch_id' => $original->branch_id,
                    'room_id' => $roomId,
                    'appointment_type_id' => $original->appointment_type_id,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'duration_minutes' => $validated['duration_minutes'],
                    'status' => 'scheduled',
                    'reason' => $validated['reason'] ?? $original->reason,
                    'notes' => $original->notes,
                    'rescheduled_from_id' => $original->id,
                ]);
            });
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['start_time' => $e->getMessage()]);
        }

        $this->auditLogger->logTenant('appointment.rescheduled', 'Appointment', $rescheduled->id, [
            'original_appointment_id' => $original->id,
            'new_start_time' => $rescheduled->start_time->toIso8601String(),
        ]);

        return redirect()->back()->with('success', 'Cita reprogramada exitosamente conservando el historial.');
    }

    /**
     * Create a schedule block.
     */
    public function createBlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'uuid', 'exists:tenant.branches,id'],
            'professional_id' => ['nullable', 'uuid', 'exists:tenant.professionals,id'],
            'room_id' => ['nullable', 'uuid', 'exists:tenant.rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:50'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
        ]);

        $block = ScheduleBlock::create([
            'branch_id' => $validated['branch_id'],
            'professional_id' => $validated['professional_id'] ?? null,
            'room_id' => $validated['room_id'] ?? null,
            'title' => $validated['title'],
            'reason' => $validated['reason'],
            'start_time' => Carbon::parse($validated['start_time']),
            'end_time' => Carbon::parse($validated['end_time']),
            'created_by_user_id' => Auth::guard('web')->id(),
        ]);

        $this->auditLogger->logTenant('schedule_block.created', 'ScheduleBlock', $block->id, [
            'title' => $block->title,
            'reason' => $block->reason,
        ]);

        return redirect()->back()->with('success', 'Bloqueo de agenda registrado exitosamente.');
    }
}
