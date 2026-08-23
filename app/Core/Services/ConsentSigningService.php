<?php

namespace App\Core\Services;

use App\Core\Models\ConsentTemplate;
use App\Core\Models\Patient;
use App\Core\Models\PatientConsent;
use InvalidArgumentException;

class ConsentSigningService
{
    /**
     * Sign and seal an informed consent for a patient.
     */
    public function sign(
        Patient $patient,
        ConsentTemplate $template,
        string $signedByName,
        ?string $signedByIdentification,
        string $relationship,
        string $signatureType,
        string $signatureData,
        ?string $ipAddress = null
    ): PatientConsent {
        if (! $template->is_active) {
            throw new InvalidArgumentException('La plantilla de consentimiento seleccionada está inactiva.');
        }

        $now = now();

        // Render template with patient metadata
        $renderedContent = str_replace(
            ['{{patient_name}}', '{{record_number}}', '{{date}}'],
            [$patient->full_name, $patient->record_number, $now->toDateString()],
            $template->content
        );

        $payload = [
            'patient_id' => $patient->id,
            'template_id' => $template->id,
            'template_version' => $template->version,
            'signed_by' => $signedByName,
            'identification' => $signedByIdentification,
            'relationship' => $relationship,
            'signed_at' => $now->toIso8601String(),
            'signature_data_hash' => hash('sha256', $signatureData),
        ];

        ksort($payload);
        $integrityHash = hash('sha256', (string) json_encode($payload));

        return PatientConsent::create([
            'patient_id' => $patient->id,
            'consent_template_id' => $template->id,
            'template_version' => $template->version,
            'title' => $template->title,
            'rendered_content' => $renderedContent,
            'signed_by_name' => $signedByName,
            'signed_by_identification' => $signedByIdentification,
            'relationship' => $relationship,
            'signature_type' => $signatureType,
            'signature_data' => $signatureData,
            'signed_at' => $now,
            'signed_ip' => $ipAddress,
            'integrity_hash' => $integrityHash,
        ]);
    }
}
