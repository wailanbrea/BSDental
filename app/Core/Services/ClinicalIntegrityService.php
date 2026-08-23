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
     * Build the deterministic snapshot protected by the clinical seal.
     *
     * @return array<string, mixed>
     */
    public function buildSnapshot(
        ClinicalEncounter $encounter,
        string $finalizedAt,
        string $finalizedBy,
        bool $includeClinicalContent = true
    ): array {
        $snapshot = [
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'professional_id' => $encounter->professional_id,
            'encounter_date' => $encounter->encounter_date->toIso8601String(),
            'chief_complaint' => $encounter->chief_complaint,
            'physical_examination' => $encounter->physical_examination,
            'vital_signs' => $encounter->vital_signs,
            'finalized_at' => $finalizedAt,
            'finalized_by' => $finalizedBy,
        ];

        if (! $includeClinicalContent) {
            return $snapshot;
        }

        $encounter->loadMissing(['evolution', 'diagnoses', 'prescriptions']);

        $snapshot['evolution'] = $encounter->evolution === null ? null : [
            'subjective' => $encounter->evolution->subjective,
            'objective' => $encounter->evolution->objective,
            'assessment' => $encounter->evolution->assessment,
            'plan' => $encounter->evolution->plan,
            'treatment_performed' => $encounter->evolution->treatment_performed,
            'recommendations' => $encounter->evolution->recommendations,
        ];
        $snapshot['diagnoses'] = $encounter->diagnoses
            ->sortBy('id')
            ->values()
            ->map(fn ($diagnosis) => [
                'code' => $diagnosis->code,
                'description' => $diagnosis->description,
                'type' => $diagnosis->type,
            ])->all();
        $snapshot['prescriptions'] = $encounter->prescriptions
            ->sortBy('id')
            ->values()
            ->map(fn ($prescription) => [
                'medication_name' => $prescription->medication_name,
                'dosage' => $prescription->dosage,
                'frequency' => $prescription->frequency,
                'duration' => $prescription->duration,
                'instructions' => $prescription->instructions,
            ])->all();

        return $snapshot;
    }

    /**
     * Verify the stored seal, preserving compatibility with legacy header-only seals.
     *
     * @return array{status: string, algorithm: string, checked_at: string}
     */
    public function verify(ClinicalEncounter $encounter): array
    {
        if ($encounter->integrity_hash === null || $encounter->finalized_at === null || $encounter->finalized_by_user_id === null) {
            return ['status' => 'not_sealed', 'algorithm' => 'none', 'checked_at' => now()->toIso8601String()];
        }

        $finalizedAt = $encounter->finalized_at->toIso8601String();
        $finalizedBy = $encounter->finalized_by_user_id;
        $fullHash = $this->computeHash($this->buildSnapshot($encounter, $finalizedAt, $finalizedBy));

        if (hash_equals($encounter->integrity_hash, $fullHash)) {
            return ['status' => 'verified', 'algorithm' => 'sha256-full-clinical-v2', 'checked_at' => now()->toIso8601String()];
        }

        $legacyHash = $this->computeHash($this->buildSnapshot($encounter, $finalizedAt, $finalizedBy, false));

        if (hash_equals($encounter->integrity_hash, $legacyHash)) {
            return ['status' => 'legacy', 'algorithm' => 'sha256-header-v1', 'checked_at' => now()->toIso8601String()];
        }

        return ['status' => 'mismatch', 'algorithm' => 'sha256-full-clinical-v2', 'checked_at' => now()->toIso8601String()];
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

        $snapshot = $this->buildSnapshot($encounter, $now->toIso8601String(), $userId);

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
