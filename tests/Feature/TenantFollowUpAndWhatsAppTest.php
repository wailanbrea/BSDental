<?php

use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\Branch;
use App\Core\Models\CrmStage;
use App\Core\Models\FollowUpTask;
use App\Core\Models\NotificationLog;
use App\Core\Models\Patient;
use App\Core\Models\Professional;
use App\Core\Models\Room;
use App\Core\Security\Models\TenantAuditLog;
use App\Core\Services\WhatsApp\AppointmentReminderScheduler;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::connection('landlord')->dropAllTables();
    Artisan::call('migrate', [
        '--path' => 'database/migrations/landlord',
        '--database' => 'landlord',
        '--realpath' => false,
    ]);

    $this->dbPathWa = database_path('tenant_gate_wa_test.sqlite');
    if (! file_exists($this->dbPathWa)) {
        touch($this->dbPathWa);
    }

    $this->tenant = Tenant::create([
        'name' => 'Clínica CRM WhatsApp Test',
        'slug' => 'crm-wa-test',
        'database_name' => $this->dbPathWa,
        'status' => 'active',
    ]);

    TenantDomain::create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'crm.bsdental.test',
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
            'name' => 'Coordinadora Dental',
            'email' => 'crm@bsdental.test',
            'password' => Hash::make('Password123!'),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'name' => 'Sede CRM Norte',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->room = Room::create([
            'branch_id' => $this->branch->id,
            'name' => 'Sillón CRM 01',
            'status' => 'active',
        ]);

        $this->professional = Professional::create([
            'first_name' => 'Dr. Carlos',
            'last_name' => 'Ortodoncista',
            'license_number' => 'COL-7766',
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'record_number' => 'HC-00099',
            'first_name' => 'Daniela',
            'last_name' => 'Morales',
            'phone' => '+58 414 999-8877',
            'status' => 'active',
        ]);

        CrmStage::create([
            'name' => 'Presupuesto Presentado',
            'slug' => 'quoted',
            'order_index' => 1,
        ]);
    });
});

test('[GATE WA / GATE CRM] Follow-up task lifecycle and strict reminder invalidation on cancellation / rescheduling', function () {
    $context = app(TenantContext::class);
    $context->makeCurrent($this->tenant);

    $this->actingAs($this->user, 'web');

    // 1. Create Follow-up Task
    $taskResponse = $this->post('http://crm.bsdental.test/crm/tasks', [
        'patient_id' => $this->patient->id,
        'type' => 'post_op',
        'title' => 'Control Post-Operatorio 48h - Cirugía de Tercer Molar',
        'due_date' => Carbon::tomorrow()->toDateString(),
        'priority' => 'high',
        'notes' => 'Verificar inflamación y toma de analgésicos',
    ]);
    $taskResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $task = FollowUpTask::where('patient_id', $this->patient->id)->firstOrFail();
    expect($task->status)->toBe('pending')
        ->and($task->priority)->toBe('high');

    // 2. Complete Task
    $completeResponse = $this->post("http://crm.bsdental.test/crm/tasks/{$task->id}/complete");
    $completeResponse->assertRedirect();

    $context->makeCurrent($this->tenant);
    $task->refresh();
    expect($task->status)->toBe('completed')
        ->and($task->completed_at)->not->toBeNull();

    // 3. Create Appointment for next week (72 hours in the future)
    $appStart = Carbon::now()->addDays(4)->setTime(10, 0);
    $appointment = Appointment::create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'room_id' => $this->room->id,
        'professional_id' => $this->professional->id,
        'start_time' => $appStart,
        'end_time' => $appStart->copy()->addMinutes(45),
        'duration_minutes' => 45,
        'status' => 'scheduled',
    ]);

    // 4. Schedule WhatsApp Reminders (48h, 24h, 2h)
    $scheduler = app(AppointmentReminderScheduler::class);
    $scheduler->scheduleReminders($appointment);

    $scheduledLogs = NotificationLog::where('appointment_id', $appointment->id)
        ->where('status', 'scheduled')
        ->get();
    expect($scheduledLogs->count())->toBe(3);

    // 5. Invariant: Rescheduling appointment cancels old reminders and generates new ones
    $newStart = Carbon::now()->addDays(7)->setTime(15, 0);
    $appointment->update([
        'start_time' => $newStart,
        'end_time' => $newStart->copy()->addMinutes(45),
    ]);
    $scheduler->rescheduleReminders($appointment);

    // Verify no obsolete reminders are active
    $cancelledLogs = NotificationLog::where('appointment_id', $appointment->id)
        ->where('status', 'cancelled')
        ->count();
    $activeLogs = NotificationLog::where('appointment_id', $appointment->id)
        ->where('status', 'scheduled')
        ->count();

    expect($cancelledLogs)->toBe(3)
        ->and($activeLogs)->toBe(3);

    // 6. Invariant: Cancelling appointment cancels all active reminders
    $scheduler->cancelReminders($appointment, 'appointment_cancelled');
    $remainingActive = NotificationLog::where('appointment_id', $appointment->id)
        ->where('status', 'scheduled')
        ->count();
    expect($remainingActive)->toBe(0);

    // 7. WhatsApp Webhook: Inbound "SI" message automatically confirms appointment
    $testLog = NotificationLog::create([
        'patient_id' => $this->patient->id,
        'appointment_id' => $appointment->id,
        'channel' => 'whatsapp',
        'recipient' => $this->patient->phone,
        'status' => 'sent',
        'content' => '¿Confirma su cita?',
        'provider_message_id' => 'wamid.123456789',
        'scheduled_at' => now(),
    ]);

    $webhookResponse = $this->postJson('http://crm.bsdental.test/api/webhooks/whatsapp', [
        'provider_message_id' => 'wamid.123456789',
        'from_phone' => $this->patient->phone,
        'message_text' => 'SI',
    ]);
    $webhookResponse->assertOk()
        ->assertJson(['status' => 'success', 'matched' => true]);

    $context->makeCurrent($this->tenant);
    $appointment->refresh();
    $testLog->refresh();

    expect($appointment->status)->toBe('confirmed')
        ->and($testLog->status)->toBe('responded');

    // 8. Verify Audit Logs
    expect(TenantAuditLog::where('action', 'crm.task_created')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'crm.task_completed')->exists())->toBeTrue()
        ->and(TenantAuditLog::where('action', 'appointment.whatsapp_confirmed')->exists())->toBeTrue();
});
