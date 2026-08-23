<?php

namespace App\Core\Services\WhatsApp;

use App\Core\Models\Appointment;
use App\Core\Models\NotificationLog;

class AppointmentReminderScheduler
{
    public function __construct(
        protected WhatsAppProviderInterface $provider
    ) {}

    /**
     * Schedule 48h, 24h, 2h reminders for an appointment.
     */
    public function scheduleReminders(Appointment $appointment): void
    {
        $patient = $appointment->patient;
        if (empty($patient->phone)) {
            return;
        }

        $start = $appointment->start_time;

        $intervals = [
            '48h' => $start->copy()->subHours(48),
            '24h' => $start->copy()->subHours(24),
            '2h' => $start->copy()->subHours(2),
        ];

        foreach ($intervals as $label => $scheduledTime) {
            if ($scheduledTime->isPast()) {
                continue;
            }

            $dateFormatted = $start->format('d/m/Y H:i');
            $content = "Hola {$patient->first_name}, le recordamos su cita odontológica para el {$dateFormatted}. Responda SI para confirmar.";

            NotificationLog::create([
                'patient_id' => $patient->id,
                'appointment_id' => $appointment->id,
                'channel' => 'whatsapp',
                'recipient' => $patient->phone,
                'status' => 'scheduled',
                'content' => $content,
                'scheduled_at' => $scheduledTime,
            ]);
        }
    }

    /**
     * Invariant: Cancel any active pending reminder for an appointment.
     */
    public function cancelReminders(Appointment $appointment, string $reason = 'cancelled'): int
    {
        return NotificationLog::where('appointment_id', $appointment->id)
            ->where('status', 'scheduled')
            ->update([
                'status' => 'cancelled',
                'error_message' => "Cancelado por {$reason}",
            ]);
    }

    /**
     * Invariant: Reschedule reminders cleanly without leaving stale reminders active.
     */
    public function rescheduleReminders(Appointment $appointment): void
    {
        $this->cancelReminders($appointment, 'reprogrammed');
        $this->scheduleReminders($appointment);
    }

    /**
     * Send a reminder immediately through the provider.
     */
    public function dispatchLog(NotificationLog $log): void
    {
        $res = $this->provider->sendMessage($log->recipient, $log->content);

        $log->update([
            'status' => $res['status'],
            'provider_message_id' => $res['provider_id'],
            'sent_at' => now(),
        ]);
    }
}
