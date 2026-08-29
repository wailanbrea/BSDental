<?php

namespace Database\Seeders;

use App\Core\Models\Odontogram;
use App\Core\Models\Patient;
use App\Core\Models\PeriodontalExam;
use App\Core\Models\PeriodontalMeasurement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class PeriodontalDemoSeeder extends Seeder
{
    public function run(): void
    {
        $patient = Patient::query()->where('record_number', 'HC-00001')->first();

        if (! $patient) {
            Log::warning('No se encontró el paciente demo HC-00001; se omitió el periodontograma.');

            return;
        }

        $odontogram = Odontogram::query()->firstOrCreate(
            ['patient_id' => $patient->id, 'type' => 'initial'],
            ['notes' => 'Odontograma inicial de valoración'],
        );
        $exam = PeriodontalExam::query()->firstOrCreate(
            ['odontogram_id' => $odontogram->id, 'status' => 'draft'],
            ['notes' => 'Periodontograma demostrativo de las piezas 16 y 21', 'recorded_at' => now()->subDays(7)],
        );

        $measurements = [
            16 => [
                'mb' => [3, 0, false, true, false, 0, 0],
                'b' => [3, 0, false, true, false, 0, 1],
                'db' => [4, 0, true, true, false, 0, 0],
                'ml' => [3, 0, false, false, false, 0, 0],
                'l' => [3, 0, false, false, false, 0, 1],
                'dl' => [4, 0, true, true, false, 0, 0],
            ],
            21 => [
                'mb' => [3, 0, false, false, false, 0, null],
                'b' => [2, 1, false, true, false, 0, null],
                'db' => [3, 0, false, false, false, 0, null],
                'ml' => [2, 0, false, false, false, 0, null],
                'l' => [2, 0, false, false, false, 0, null],
                'dl' => [3, 0, false, false, false, 0, null],
            ],
        ];

        foreach ($measurements as $toothNumber => $sites) {
            foreach ($sites as $site => [$depth, $recession, $bleeding, $plaque, $suppuration, $mobility, $furcation]) {
                PeriodontalMeasurement::query()->firstOrCreate(
                    ['periodontal_exam_id' => $exam->id, 'tooth_number' => $toothNumber, 'site' => $site],
                    [
                        'probing_depth' => $depth,
                        'recession' => $recession,
                        'bleeding' => $bleeding,
                        'plaque' => $plaque,
                        'suppuration' => $suppuration,
                        'mobility' => $mobility,
                        'furcation' => $furcation,
                        'is_implant' => false,
                    ],
                );
            }
        }
    }
}
