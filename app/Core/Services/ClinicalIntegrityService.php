<?php

namespace App\Core\Services;

use App\Core\Models\ClinicalAmendment;
use App\Core\Models\ClinicalEncounter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClinicalIntegrityService
{
    /**
     * Compute SHA-256 integrity hash for an encounter snapshot.
     *
     * @param  array<string, mixed>  $payload
     */
    public function computeHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload));
    }

    /**
     * Finalize an encounter, sealing it against direct modifications.
     */
    public function finalize(ClinicalEncounter $encounter, string $userId): ClinicalEncounter
    {
        if ($encounter->status === 'finalized' || $encounter->status === 'amended') {
            throw new InvalidArgumentException('El encuentro clínico ya se encuentra finalizado e inmutable.');
        }

        $now = now();

        $snapshot = [
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'professional_id' => $encounter->professional_id,
            'encounter_date' => $encounter->encounter_date->toIso8601String(),
            'chief_complaint' => $encounter->chief_complaint,
            'physical_examination' => $encounter->physical_examination,
            'vital_signs' => $encounter->vital_signs,
            'finalized_at' => $now->toIso8601String(),
            'finalized_by' => $userId,
        ];

        $hash = $this->computeHash($snapshot);

        $encounter->update([
            'status' => 'finalized',
            'finalized_at' => $now,
            'finalized_by_user_id' => $userId,
            'integrity_hash' => $hash,
        ]);

        return $encounter;
    }

    /**
     * Create a clinical amendment for an already finalized encounter.
     *
     * @param  array<string, mixed>  $amendedContent
     */
    public function createAmendment(
        ClinicalEncounter $encounter,
        string $userId,
        string $reason,
        array $amendedContent
    ): ClinicalAmendment {
        if ($encounter->status !== 'finalized' && $encounter->status !== 'amended') {
            throw new InvalidArgumentException('Solo se pueden realizar enmiendas a encuentros clínicos previamente finalizados.');
        }

        if (empty(trim($reason))) {
            throw new InvalidArgumentException('La justificación médica para la enmienda es obligatoria.');
        }

        return DB::connection('tenant')->transaction(function () use ($encounter, $userId, $reason, $amendedContent) {
            $now = now();

            $amendmentSnapshot = [
                'encounter_id' => $encounter->id,
                'previous_hash' => $encounter->integrity_hash,
                'reason' => $reason,
                'amended_content' => $amendedContent,
                'amended_by' => $userId,
                'amended_at' => $now->toIso8601String(),
            ];

            $amendmentHash = $this->computeHash($amendmentSnapshot);

            $amendment = ClinicalAmendment::create([
                'encounter_id' => $encounter->id,
                'reason' => $reason,
                'amended_content' => $amendedContent,
                'amended_by_user_id' => $userId,
                'amended_at' => $now,
                'integrity_hash' => $amendmentHash,
            ]);

            $encounter->update([
                'status' => 'amended',
            ]);

            return $amendment;
        });
    }
}
