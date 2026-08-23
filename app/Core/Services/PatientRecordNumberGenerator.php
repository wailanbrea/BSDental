<?php

namespace App\Core\Services;

use App\Core\Models\Patient;

class PatientRecordNumberGenerator
{
    /**
     * Generate the next unique clinical record number for the tenant.
     */
    public function generate(): string
    {
        $count = Patient::withTrashed()->count() + 1;

        do {
            $formatted = sprintf('HC-%05d', $count);
            $exists = Patient::withTrashed()->where('record_number', $formatted)->exists();
            if ($exists) {
                $count++;
            }
        } while ($exists);

        return $formatted;
    }
}
