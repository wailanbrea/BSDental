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
            $surfaces = $entry->surfaces ?: [$entry->surface];
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
                'surfaces' => $surfaces,
                'entry_type' => $entry->entry_type,
                'clinical_status' => $entry->clinical_status,
                'verification_status' => $entry->verification_status,
                'clinical_code' => $entry->clinical_code,
                'clinical_display' => $entry->clinical_display,
                'supersedes_entry_id' => $entry->supersedes_entry_id,
                'amendment_reason' => $entry->amendment_reason,
                'lifecycle_state' => $entry->lifecycle_state,
                'recorded_at' => $entry->recorded_at->toIso8601String(),
                'notes' => $entry->notes,
            ];

            foreach ($surfaces as $surface) {
                if ($surface === 'all') {
                    continue;
                }

                $matrix[$tooth]['surfaces'][$surface] = [
                    'condition' => $entry->condition,
                    'lifecycle_state' => $entry->lifecycle_state,
                    'clinical_status' => $entry->clinical_status,
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
        return $this->recordClinicalEntry($odontogram, [
            'tooth_number' => $toothNumber,
            'condition' => $condition,
            'surfaces' => [$surface],
            'surface' => $surface,
            'lifecycle_state' => $lifecycleState,
            'notes' => $notes,
            'encounter_id' => $encounterId,
        ], $userId);
    }

    /**
     * Append an immutable, structured clinical event to the odontogram.
     *
     * @param array<string, mixed> $data
     */
    public function recordClinicalEntry(Odontogram $odontogram, array $data, ?string $userId = null): OdontogramEntry
    {
        $surfaces = array_values(array_unique($data['surfaces'] ?? [$data['surface'] ?? 'all']));
        if (in_array('all', $surfaces, true) && count($surfaces) > 1) {
            $surfaces = ['all'];
        }

        return OdontogramEntry::create([
            'odontogram_id' => $odontogram->id,
            'encounter_id' => $data['encounter_id'] ?? null,
            'tooth_number' => $data['tooth_number'],
            'surface' => $surfaces[0],
            'surfaces' => $surfaces,
            'condition' => $data['condition'],
            'entry_type' => $data['entry_type'] ?? $this->entryTypeForCondition($data['condition']),
            'code_system' => $data['code_system'] ?? null,
            'clinical_code' => $data['clinical_code'] ?? null,
            'clinical_display' => $data['clinical_display'] ?? $data['condition'],
            'clinical_status' => $data['clinical_status'] ?? 'active',
            'verification_status' => $data['verification_status'] ?? 'confirmed',
            'procedure_id' => $data['procedure_id'] ?? null,
            'supersedes_entry_id' => $data['supersedes_entry_id'] ?? null,
            'amendment_reason' => $data['amendment_reason'] ?? null,
            'device_details' => $data['device_details'] ?? null,
            'lifecycle_state' => $data['lifecycle_state'] ?? 'initial_diagnosis',
            'notes' => $data['notes'] ?? null,
            'recorded_by_user_id' => $userId,
            'recorded_at' => now(),
        ]);
    }

    public function entryTypeForCondition(string $condition): string
    {
        return match ($condition) {
            'caries', 'fracture' => 'diagnosis',
            'missing' => 'anatomical_state',
            'implant' => 'device',
            'restored_composite', 'restored_amalgam', 'crown', 'endodontic', 'prosthesis', 'sealant' => 'procedure',
            default => 'finding',
        };
    }
}
