<?php

namespace App\Core\Services;

use App\Core\Models\ConsentTemplate;
use App\Core\Models\Patient;
use App\Core\Models\PatientConsent;
use InvalidArgumentException;

class ConsentSigningService
{
    public const ALGORITHM = 'sha256-consent-v2';

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

        if ($template->required_witness) {
            throw new InvalidArgumentException('Esta plantilla requiere la firma de un testigo y no puede completarse como firma individual.');
        }

        $now = now();

        // Render template with patient metadata
        $renderedContent = str_replace(
            ['{{patient_name}}', '{{record_number}}', '{{date}}'],
            [$patient->full_name, $patient->record_number, $now->toDateString()],
            $template->content
        );

        $integrityHash = $this->hashPayload([
            'patient_id' => $patient->id,
            'consent_template_id' => $template->id,
            'template_version' => $template->version,
            'title' => $template->title,
            'rendered_content' => $renderedContent,
            'signed_by_name' => $signedByName,
            'signed_by_identification' => $signedByIdentification,
            'relationship' => $relationship,
            'signature_type' => $signatureType,
            'signature_data_hash' => hash('sha256', $signatureData),
            'signed_at' => $now->toIso8601String(),
        ]);

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

    /**
     * Verify the immutable consent snapshot while retaining legacy compatibility.
     *
     * @return array{status: 'verified'|'legacy'|'mismatch', algorithm: string}
     */
    public function verify(PatientConsent $consent): array
    {
        $current = $this->hashPayload([
            'patient_id' => $consent->patient_id,
            'consent_template_id' => $consent->consent_template_id,
            'template_version' => $consent->template_version,
            'title' => $consent->title,
            'rendered_content' => $consent->rendered_content,
            'signed_by_name' => $consent->signed_by_name,
            'signed_by_identification' => $consent->signed_by_identification,
            'relationship' => $consent->relationship,
            'signature_type' => $consent->signature_type,
            'signature_data_hash' => hash('sha256', $consent->signature_data),
            'signed_at' => $consent->signed_at->toIso8601String(),
        ]);

        if (hash_equals($consent->integrity_hash, $current)) {
            return ['status' => 'verified', 'algorithm' => self::ALGORITHM];
        }

        $legacyPayload = [
            'patient_id' => $consent->patient_id,
            'template_id' => $consent->consent_template_id,
            'template_version' => $consent->template_version,
            'signed_by' => $consent->signed_by_name,
            'identification' => $consent->signed_by_identification,
            'relationship' => $consent->relationship,
            'signed_at' => $consent->signed_at->toIso8601String(),
            'signature_data_hash' => hash('sha256', $consent->signature_data),
        ];
        ksort($legacyPayload);
        $legacy = hash('sha256', (string) json_encode($legacyPayload));

        if (hash_equals($consent->integrity_hash, $legacy)) {
            return ['status' => 'legacy', 'algorithm' => 'sha256-consent-v1'];
        }

        return ['status' => 'mismatch', 'algorithm' => self::ALGORITHM];
    }

    /**
     * @param  array<string, bool|int|string|null>  $payload
     */
    private function hashPayload(array $payload): string
    {
        ksort($payload);

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
