<?php

namespace App\Core\Services;

use App\Core\Models\PatientCharge;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;

class ProfessionalCompensationService
{
    /**
     * Accrue professional commission with frozen rule snapshot.
     */
    public function accrueCommission(
        Professional $professional,
        PatientCharge $charge,
        float $rate,
        string $ruleType = 'percentage_production'
    ): ProfessionalCompensation {
        $baseAmount = $charge->amount;
        $commissionAmount = $baseAmount * ($rate / 100);

        return ProfessionalCompensation::create([
            'professional_id' => $professional->id,
            'patient_charge_id' => $charge->id,
            'rule_type' => $ruleType,
            'rate' => $rate,
            'base_amount' => $baseAmount,
            'commission_amount' => $commissionAmount,
            'status' => 'accrued',
            'accrued_at' => now(),
        ]);
    }
}
