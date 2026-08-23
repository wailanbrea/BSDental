<?php

namespace App\Core\Services;

use App\Core\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

class PatientDuplicateDetector
{
    /**
     * Search for duplicate candidates matching identification, phone, or exact name.
     *
     * @return Collection<int, Patient>
     */
    public function findCandidates(
        ?string $identificationNumber,
        ?string $phone,
        ?string $firstName,
        ?string $lastName,
        ?string $ignorePatientId = null
    ): Collection {
        $query = Patient::query();

        if ($ignorePatientId) {
            $query->where('id', '!=', $ignorePatientId);
        }

        $hasCriteria = false;

        $query->where(function ($q) use ($identificationNumber, $phone, $firstName, $lastName, &$hasCriteria) {
            if (! empty($identificationNumber)) {
                $q->orWhere('identification_number', trim($identificationNumber));
                $hasCriteria = true;
            }

            if (! empty($phone)) {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                if (! empty($cleanPhone)) {
                    $q->orWhere('phone', 'like', "%{$cleanPhone}%");
                    $hasCriteria = true;
                }
            }

            if (! empty($firstName) && ! empty($lastName)) {
                $q->orWhere(function ($sub) use ($firstName, $lastName) {
                    $sub->where('first_name', 'like', trim($firstName))
                        ->where('last_name', 'like', trim($lastName));
                });
                $hasCriteria = true;
            }
        });

        if (! $hasCriteria) {
            return new Collection;
        }

        return $query->take(5)->get();
    }
}
