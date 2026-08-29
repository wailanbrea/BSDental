<?php

namespace App\Core\Controllers;

use App\Core\Models\Appointment;
use App\Core\Models\NotificationLog;
use App\Http\Controllers\Controller;
use App\Platform\Security\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    /**
     * Handle incoming WhatsApp Webhook (Signed & Idempotent).
     */
    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider_message_id' => ['nullable', 'string'],
            'from_phone' => ['required', 'string'],
            'message_text' => ['required', 'string'],
            'status' => ['nullable', 'string', 'in:delivered,read,responded,failed'],
        ]);

        $phone = $validated['from_phone'];
        $text = strtoupper(trim($validated['message_text']));
        $providerMsgId = $validated['provider_message_id'] ?? null;

        // Find matching notification log
        $log = null;
        if (! empty($providerMsgId)) {
            $log = NotificationLog::where('provider_message_id', $providerMsgId)->first();
        }

        if (! $log) {
            $log = NotificationLog::where('recipient', $phone)->latest()->first();
        }

        if ($log) {
            $log->update([
                'status' => 'responded',
            ]);

            // If patient confirms with "SI", confirm appointment
            if (in_array($text, ['SI', 'CONFIRMAR', 'CONFIRMO', 'YES', 'OK']) && $log->appointment_id) {
                $appointment = Appointment::find($log->appointment_id);
                if ($appointment && Appointment::canTransition($appointment->status, 'confirmed')) {
                    $oldStatus = $appointment->status;
                    $appointment->update([
                        'status' => 'confirmed',
                    ]);

                    $this->auditLogger->logTenant('appointment.status_updated', 'Appointment', $appointment->id, [
                        'old_status' => $oldStatus,
                        'new_status' => 'confirmed',
                        'reason' => 'Confirmación recibida por WhatsApp.',
                        'source' => 'whatsapp_webhook',
                    ]);

                    $this->auditLogger->logTenant('appointment.whatsapp_confirmed', 'Appointment', $appointment->id, [
                        'patient_id' => $appointment->patient_id,
                        'source' => 'whatsapp_webhook',
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'matched' => (bool) $log,
        ]);
    }
}
