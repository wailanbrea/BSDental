<?php

namespace App\Core\Services;

use App\Core\Models\Odontogram;
use App\Core\Models\OdontogramEntry;

class OdontogramService
{
    /**
     * Get or create the odontogram for a patient.
     */
    public function getOrCreateForPatient(string $patientId, string $type = 'adult'): Odontogram
    {
        return Odontogram::firstOrCreate(
            ['patient_id' => $patientId],
            ['type' => $type]
        );
    }

    /**
     * Compute current tooth state matrix by consolidating all historical entries.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToothMatrix(Odontogram $odontogram): array
    {
        $entries = $odontogram->entries()->get();

        $matrix = [];

        /** @var OdontogramEntry $entry */
        foreach ($entries as $entry) {
            $tooth = $entry->tooth_number;
            if (! isset($matrix[$tooth])) {
                $matrix[$tooth] = [
                    'tooth_number' => $tooth,
                    'conditions' => [],
                    'surfaces' => [],
                    'latest_state' => $entry->lifecycle_state,
                ];
            }

            $matrix[$tooth]['conditions'][] = [
                'id' => $entry->id,
                'condition' => $entry->condition,
                'surface' => $entry->surface,
                'lifecycle_state' => $entry->lifecycle_state,
                'recorded_at' => $entry->recorded_at->toIso8601String(),
                'notes' => $entry->notes,
            ];

            if ($entry->surface !== 'all') {
                $matrix[$tooth]['surfaces'][$entry->surface] = [
                    'condition' => $entry->condition,
                    'lifecycle_state' => $entry->lifecycle_state,
                ];
            }

            $matrix[$tooth]['latest_state'] = $entry->lifecycle_state;
        }

        return $matrix;
    }

    /**
     * Record a new condition or state change on a tooth/surface.
     */
    public function recordCondition(
        Odontogram $odontogram,
        int $toothNumber,
        string $condition,
        string $surface = 'all',
        string $lifecycleState = 'initial_diagnosis',
        ?string $notes = null,
        ?string $encounterId = null,
        ?string $userId = null
    ): OdontogramEntry {
        return OdontogramEntry::create([
            'odontogram_id' => $odontogram->id,
            'encounter_id' => $encounterId,
            'tooth_number' => $toothNumber,
            'surface' => $surface,
            'condition' => $condition,
            'lifecycle_state' => $lifecycleState,
            'notes' => $notes,
            'recorded_by_user_id' => $userId,
            'recorded_at' => now(),
        ]);
    }
}
