<?php

namespace App\Core\Services;

use App\Core\Models\Appointment;
use App\Core\Models\ScheduleBlock;
use Carbon\Carbon;
use InvalidArgumentException;

class AppointmentConflictValidator
{
    /**
     * Validate that an appointment does not collide with professional, room, or schedule blocks.
     *
     * @throws InvalidArgumentException
     */
    public function validate(
        string $professionalId,
        string $branchId,
        Carbon $startTime,
        Carbon $endTime,
        ?string $roomId = null,
        ?string $ignoreAppointmentId = null
    ): void {
        if ($startTime->gte($endTime)) {
            throw new InvalidArgumentException('La hora de inicio debe ser anterior a la hora de fin.');
        }

        // 1. Check Professional Overlap
        $professionalConflict = Appointment::where('professional_id', $professionalId)
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($sub) use ($startTime, $endTime) {
                    $sub->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                });
            })
            ->exists();

        if ($professionalConflict) {
            throw new InvalidArgumentException('El profesional seleccionado ya tiene una cita agendada en ese horario.');
        }

        // 2. Check Room Overlap (if room specified)
        if ($roomId) {
            $roomConflict = Appointment::where('room_id', $roomId)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
                ->where(function ($q) use ($startTime, $endTime) {
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $startTime);
                })
                ->exists();

            if ($roomConflict) {
                throw new InvalidArgumentException('El consultorio / sillón dental ya está reservado en ese horario.');
            }
        }

        // 3. Check Schedule Blocks
        $blockConflict = ScheduleBlock::where('branch_id', $branchId)
            ->where(function ($q) use ($professionalId, $roomId) {
                $q->where(function ($sub) use ($professionalId) {
                    $sub->where('professional_id', $professionalId);
                })->orWhere(function ($sub) use ($roomId) {
                    if ($roomId) {
                        $sub->where('room_id', $roomId);
                    }
                })->orWhere(function ($sub) {
                    $sub->whereNull('professional_id')->whereNull('room_id'); // Global branch block
                });
            })
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($blockConflict) {
            throw new InvalidArgumentException('El horario seleccionado coincide con un bloqueo de agenda activo.');
        }
    }
}
