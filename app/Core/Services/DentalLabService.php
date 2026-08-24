<?php

namespace App\Core\Services;

use App\Core\Models\DentalLaboratory;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
     * Transition order status (LAB-01).
     */
    public function updateStatus(
        LabOrder $order,
        string $newStatus,
        ?float $finalCost = null
    ): LabOrder {
        $validStatuses = ['draft', 'ordered', 'sent', 'in_progress', 'ready', 'received', 'delivered', 'rejected_remake', 'cancelled'];
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

    /**
     * Receive order with quality check notes (LAB-02).
     */
    public function receiveWithQualityCheck(
        LabOrder $order,
        float $finalCost,
        string $qualityNotes,
        ?string $userId = null
    ): LabOrder {
        if ($finalCost < 0) {
            throw new InvalidArgumentException('El costo final del laboratorio no puede ser negativo.');
        }

        $order->update([
            'status' => 'received',
            'received_date' => now(),
            'final_cost' => $finalCost,
            'quality_check_notes' => $qualityNotes,
        ]);

        return $order;
    }

    /**
     * Reject order and create a linked remake order with reason and quality check trace (LAB-02).
     */
    public function rejectAndRemakeOrder(
        LabOrder $originalOrder,
        string $remakeReason,
        ?string $shadeGuide = null,
        float $estimatedCost = 0.00,
        ?string $dueDate = null,
        ?string $userId = null
    ): LabOrder {
        if (strlen(trim($remakeReason)) < 5) {
            throw new InvalidArgumentException('El motivo del re-trabajo es obligatorio (mínimo 5 caracteres).');
        }

        return DB::connection('tenant')->transaction(function () use ($originalOrder, $remakeReason, $shadeGuide, $estimatedCost, $dueDate, $userId) {
            // Update original order to rejected_remake
            $originalOrder->update([
                'status' => 'rejected_remake',
                'remake_reason' => $remakeReason,
            ]);

            // Create new remake order linked to original
            $newOrderNumber = $this->generateOrderNumber();
            $newOrder = LabOrder::create([
                'patient_id' => $originalOrder->patient_id,
                'laboratory_id' => $originalOrder->laboratory_id,
                'treatment_plan_item_id' => $originalOrder->treatment_plan_item_id,
                'parent_order_id' => $originalOrder->id,
                'order_number' => $newOrderNumber,
                'tooth_number' => $originalOrder->tooth_number,
                'work_description' => "RE-TRABAJO ({$originalOrder->order_number}): {$originalOrder->work_description}",
                'shade_guide' => $shadeGuide ?? $originalOrder->shade_guide,
                'status' => 'ordered',
                'due_date' => $dueDate ? Carbon::parse($dueDate) : null,
                'estimated_cost' => $estimatedCost,
                'final_cost' => 0.00,
                'payable_status' => 'unpaid',
                'remake_reason' => $remakeReason,
                'created_by_user_id' => $userId,
            ]);

            $this->notificationService->notifyActiveUsers(
                'lab',
                'warning',
                "Re-trabajo solicitado: {$newOrderNumber}",
                "Se ha generado una orden de repetición para {$originalOrder->patient->full_name} debido a: {$remakeReason}",
                '/lab',
                ['original_order_id' => $originalOrder->id, 'remake_order_id' => $newOrder->id]
            );

            return $newOrder;
        });
    }
}
