<?php

namespace App\Core\Services;

use App\Core\Models\DentalLaboratory;
use App\Core\Models\LabOrder;
use App\Core\Models\Patient;
use App\Core\Models\TreatmentPlanItem;
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
        if ($treatmentPlanItemId !== null) {
            $planItem = TreatmentPlanItem::with(['treatmentPlan', 'procedure'])->findOrFail($treatmentPlanItemId);

            if ($planItem->treatmentPlan->patient_id !== $patient->id) {
                throw new InvalidArgumentException('La orden de laboratorio debe corresponder al paciente del ítem de tratamiento.');
            }

            if (! $planItem->procedure->requires_lab) {
                throw new InvalidArgumentException('El procedimiento vinculado no requiere laboratorio.');
            }
        }

        return DB::connection('tenant')->transaction(function () use ($patient, $lab, $workDescription, $toothNumber, $shadeGuide, $estimatedCost, $dueDate, $treatmentPlanItemId, $userId) {
            return LabOrder::create([
                'patient_id' => $patient->id,
                'laboratory_id' => $lab->id,
                'treatment_plan_item_id' => $treatmentPlanItemId,
                'order_number' => $this->generateOrderNumber(),
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
        });
    }

    /**
     * Transition order status (LAB-01).
     */
    public function updateStatus(
        LabOrder $order,
        string $newStatus,
        ?float $finalCost = null
    ): LabOrder {
        return DB::connection('tenant')->transaction(function () use ($order, $newStatus, $finalCost) {
            $lockedOrder = LabOrder::with('patient')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $transitions = [
                'draft' => ['ordered', 'cancelled'],
                'ordered' => ['sent', 'cancelled'],
                'sent' => ['in_progress', 'cancelled'],
                'in_progress' => ['ready', 'cancelled'],
                'ready' => ['received', 'cancelled'],
                'received' => ['delivered'],
            ];

            if ($newStatus !== $lockedOrder->status && ! in_array($newStatus, $transitions[$lockedOrder->status] ?? [], true)) {
                throw new InvalidArgumentException("Transición de laboratorio no permitida: {$lockedOrder->status} a {$newStatus}.");
            }

            if ($finalCost !== null && ! in_array($newStatus, ['received', 'delivered'], true)) {
                throw new InvalidArgumentException('El costo final solo puede registrarse al recibir o entregar la orden.');
            }

            $previousStatus = $lockedOrder->status;
            $updates = ['status' => $newStatus];

            if ($newStatus === 'sent' && ! $lockedOrder->sent_date) {
                $updates['sent_date'] = now();
            }

            if ($newStatus === 'received' && ! $lockedOrder->received_date) {
                $updates['received_date'] = now();
            }

            if ($finalCost !== null) {
                $updates['final_cost'] = $finalCost;
            }

            $lockedOrder->update($updates);

            if ($newStatus === 'ready' && $previousStatus !== 'ready') {
                $title = "Trabajo de laboratorio {$lockedOrder->order_number} listo";
                $message = "El laboratorio marcó como listo el trabajo de {$lockedOrder->patient->full_name}.";
                $data = ['lab_order_id' => $lockedOrder->id, 'patient_id' => $lockedOrder->patient_id];

                if ($lockedOrder->created_by_user_id !== null) {
                    $this->notificationService->notifyUser(
                        $lockedOrder->created_by_user_id,
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

            return $lockedOrder;
        });
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

        if ($order->status !== 'ready') {
            throw new InvalidArgumentException('Solo se pueden recibir órdenes de laboratorio listas.');
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
            if ($originalOrder->status !== 'received') {
                throw new InvalidArgumentException('Solo se puede solicitar re-trabajo de una orden recibida.');
            }
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
