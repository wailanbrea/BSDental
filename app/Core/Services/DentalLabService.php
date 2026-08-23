<?php

namespace App\Core\Services;

use App\Core\Models\DentalLaboratory;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use Carbon\Carbon;
use InvalidArgumentException;

class DentalLabService
{
    public function __construct(
        protected UserNotificationService $notificationService
    ) {}

    /**
     * Generate sequential lab order number.
     */
    public function generateOrderNumber(): string
    {
        $count = LabOrder::withTrashed()->count() + 1;

        do {
            $formatted = sprintf('LAB-%05d', $count);
            $exists = LabOrder::withTrashed()->where('order_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }

    /**
     * Create a new lab order for a dental prosthesis / work.
     */
    public function createOrder(
        Patient $patient,
        DentalLaboratory $lab,
        string $workDescription,
        ?int $toothNumber = null,
        ?string $shadeGuide = null,
        float $estimatedCost = 0.00,
        ?string $dueDate = null,
        ?string $treatmentPlanItemId = null,
        ?string $userId = null
    ): LabOrder {
        $orderNumber = $this->generateOrderNumber();

        return LabOrder::create([
            'patient_id' => $patient->id,
            'laboratory_id' => $lab->id,
            'treatment_plan_item_id' => $treatmentPlanItemId,
            'order_number' => $orderNumber,
            'tooth_number' => $toothNumber,
            'work_description' => $workDescription,
            'shade_guide' => $shadeGuide,
            'status' => 'draft',
            'due_date' => $dueDate ? Carbon::parse($dueDate) : null,
            'estimated_cost' => $estimatedCost,
            'final_cost' => 0.00,
            'payable_status' => 'unpaid',
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Transition order status.
     */
    public function updateStatus(
        LabOrder $order,
        string $newStatus,
        ?float $finalCost = null
    ): LabOrder {
        $validStatuses = ['draft', 'ordered', 'sent', 'in_progress', 'ready', 'received', 'delivered', 'cancelled'];
        if (! in_array($newStatus, $validStatuses, true)) {
            throw new InvalidArgumentException("Estado de orden inválido: {$newStatus}");
        }

        $previousStatus = $order->status;
        $updates = ['status' => $newStatus];

        if ($newStatus === 'sent' && ! $order->sent_date) {
            $updates['sent_date'] = now();
        }

        if ($newStatus === 'received' && ! $order->received_date) {
            $updates['received_date'] = now();
        }

        if ($finalCost !== null) {
            $updates['final_cost'] = $finalCost;
        }

        $order->update($updates);

        if ($newStatus === 'ready' && $previousStatus !== 'ready') {
            $title = "Trabajo de laboratorio {$order->order_number} listo";
            $message = "El laboratorio marcó como listo el trabajo de {$order->patient->full_name}.";
            $data = ['lab_order_id' => $order->id, 'patient_id' => $order->patient_id];

            if ($order->created_by_user_id !== null) {
                $this->notificationService->notifyUser(
                    $order->created_by_user_id,
                    'lab',
                    'success',
                    $title,
                    $message,
                    '/lab',
                    $data
                );
            } else {
                $this->notificationService->notifyActiveUsers(
                    'lab',
                    'success',
                    $title,
                    $message,
                    '/lab',
                    $data
                );
            }
        }

        return $order;
    }
}
