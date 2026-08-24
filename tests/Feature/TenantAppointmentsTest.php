<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\AppointmentType;
use App\Core\Models\Branch;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Models\Room;
use App\Core\Security\Models\TenantAuditLog;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathApt = $this->tenantDatabasePath('tenant_gate_apt_test.sqlite');
    if (! file_exists($this->dbPathApt)) {
        touch($this->dbPathApt);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica Dental Agenda Test',
        'slug' => 'agenda-test',
        'database_name' => $this->dbPathApt,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'agenda.bsdental.test',
        'is_primary' => true,
        'is_verified' => true,
    ]);

    $context = app(TenantContext::class);
    $context->execute($this->tenant, function () {
        Schema::connection('tenant')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--database' => 'tenant',
            '--realpath' => false,
        ]);

        $this->user = User::create([
            'name' => 'Recepcionista Carla',
            'email' => 'carla@agenda.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);
        grantTenantOwnerAccess($this->user);

        $this->branch = Branch::create([
            'name' => 'Sede Principal',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sillón Odontológico 1',
        ]);

        $this->professional = Professional::create([
            'first_name' => 'Dr. Andrés',
            'last_name' => 'Ortodoncista',
            'color' => '#0d9488',
            'is_active' => true,
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00001',
            'first_name' => 'Mateo',
            'last_name' => 'Silva',
            'phone' => '+58 414 111-2233',
            'status' => 'active',
        ]);

        $this->aptType = AppointmentType::create([
            'name' => 'Control de Ortodoncia',
            'duration_minutes' => 30,
            'color' => '#0d9488',
        ]);
    });
});

test('[GATE APP] Comprehensive appointment lifecycle, reception flow, conflict validator and rescheduling', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    $targetDate = now()->addDay()->format('Y-m-d');
    $slot1Start = "{$targetDate} 10:00:00";

    // 1. Create Appointment
    $response = $this->post('http://agenda.bsdental.test/appointments', [
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'branch_id' => $this->branch->id,
        'room_id' => $this->room->id,
        'appointment_type_id' => $this->aptType->id,
        'start_time' => $slot1Start,
        'duration_minutes' => 30,
        'reason' => 'Ajuste de brackets',
    ]);
    $response->assertRedirect();

    $context->makeCurrent($this->tenant);
    $appointment = Appointment::where('patient_id', $this->patient->id)->firstOrFail();
    expect($appointment->status)->toBe('scheduled')
        ->and($appointment->end_time->format('H:i:s'))->toBe('10:30:00');

    // Patient 360 deep links preselect the patient for creation and open an existing appointment.
    $this->get("http://agenda.bsdental.test/appointments?create=1&patient_id={$this->patient->id}&date={$targetDate}&view=day")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Appointments/Index')
            ->where('filters.patient_id', $this->patient->id)
            ->where('filters.open_create', true));

    $this->get("http://agenda.bsdental.test/appointments?appointment_id={$appointment->id}&date={$targetDate}&view=day")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.appointment_id', $appointment->id)
            ->has('appointments', 1));

    // 2. Conflict Validation: Attempt to book same professional at overlapping time
    $conflictResponse = $this->post('http://agenda.bsdental.test/appointments', [
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'branch_id' => $this->branch->id,
        'room_id' => $this->room->id,
        'start_time' => "{$targetDate} 10:15:00", // Overlaps with 10:00 - 10:30
        'duration_minutes' => 30,
    ]);
    $conflictResponse->assertSessionHasErrors(['start_time']);

    // 3. Reception State Transitions (Check-In -> In-Progress -> Completed)
    // 3.1 Check-In (Paciente llega a sala de espera)
    $checkInResponse = $this->put("http://agenda.bsdental.test/appointments/{$appointment->id}/status", [
        'status' => 'checked_in',
    ]);
    $checkInResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $appointment->refresh();
    expect($appointment->status)->toBe('checked_in')
        ->and($appointment->checked_in_at)->not->toBeNull();

    // 3.2 In Progress (Pasa a sillón dental)
    $inProgressResponse = $this->put("http://agenda.bsdental.test/appointments/{$appointment->id}/status", [
        'status' => 'in_progress',
    ]);
    $inProgressResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $appointment->refresh();
    expect($appointment->status)->toBe('in_progress')
        ->and($appointment->in_progress_at)->not->toBeNull();

    // 3.3 Completed (Finaliza atención)
    $completedResponse = $this->put("http://agenda.bsdental.test/appointments/{$appointment->id}/status", [
        'status' => 'completed',
    ]);
    $completedResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $appointment->refresh();
    expect($appointment->status)->toBe('completed')
        ->and($appointment->completed_at)->not->toBeNull();

    // 4. Create Schedule Block
    $blockResponse = $this->post('http://agenda.bsdental.test/appointments/blocks', [
        'branch_id' => $this->branch->id,
        'professional_id' => $this->professional->id,
        'title' => 'Reunión Clínica Médica',
        'reason' => 'meeting',
        'start_time' => "{$targetDate} 14:00:00",
        'end_time' => "{$targetDate} 15:00:00",
    ]);
    $blockResponse->assertRedirect();

    $this->get("http://agenda.bsdental.test/appointments?date={$targetDate}&view=day&branch_id={$this->branch->id}&professional_id={$this->professional->id}&room_id={$this->room->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Clinic/Appointments/Index')
            ->where('filters.room_id', $this->room->id)
            ->has('appointments', 1)
            ->has('blocks', 1)
            ->where('blocks.0.title', 'Reunión Clínica Médica')
        );

    // Try booking appointment during block
    $blockedBookingResponse = $this->post('http://agenda.bsdental.test/appointments', [
        'patient_id' => $this->patient->id,
        'professional_id' => $this->professional->id,
        'branch_id' => $this->branch->id,
        'start_time' => "{$targetDate} 14:15:00",
        'duration_minutes' => 30,
    ]);
    $blockedBookingResponse->assertSessionHasErrors(['start_time']);

    // 5. Reschedule Appointment
    $rescheduleResponse = $this->post("http://agenda.bsdental.test/appointments/{$appointment->id}/reschedule", [
        'start_time' => "{$targetDate} 16:00:00",
        'duration_minutes' => 30,
        'reason' => 'Reprogramación por solicitud del paciente',
    ]);
    $rescheduleResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $appointment->refresh();
    expect($appointment->status)->toBe('rescheduled');

    $newApt = Appointment::where('rescheduled_from_id', $appointment->id)->firstOrFail();
    expect($newApt->status)->toBe('scheduled')
        ->and($newApt->start_time->format('H:i:s'))->toBe('16:00:00');

    // 6. Cancellation requires a clinical-operational reason.
    $this->put("http://agenda.bsdental.test/appointments/{$newApt->id}/status", [
        'status' => 'cancelled',
    ])->assertSessionHasErrors('cancellation_reason');

    $this->put("http://agenda.bsdental.test/appointments/{$newApt->id}/status", [
        'status' => 'cancelled',
        'cancellation_reason' => 'Paciente solicitó nueva fecha por viaje.',
    ])->assertRedirect();

    $context->makeCurrent($this->tenant);
    $newApt->refresh();
    expect($newApt->status)->toBe('cancelled')
        ->and($newApt->cancelled_at)->not->toBeNull()
        ->and($newApt->cancellation_reason)->toBe('Paciente solicitó nueva fecha por viaje.');

    // 7. Verify Audit Logs, including the real previous status.
    $firstStatusAudit = TenantAuditLog::where('action', 'appointment.status_updated')->oldest('created_at')->firstOrFail();
    expect(TenantAuditLog::where('action', 'appointment.created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'appointment.status_updated')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'appointment.rescheduled')->exists())->toBeTrue()
        ->and($firstStatusAudit->metadata['old_status'])->toBe('scheduled')
        ->and($firstStatusAudit->metadata['new_status'])->toBe('checked_in');
});
