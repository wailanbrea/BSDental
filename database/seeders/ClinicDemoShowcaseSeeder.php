<?php

namespace Database\Seeders;

use App\Core\Auth\Models\User;
use App\Core\Models\Branch;
use App\Core\Models\ClinicalDiagnosis;
use App\Core\Models\ClinicalEncounter;
use App\Core\Models\ClinicalEvolution;
use App\Core\Models\ClinicalPrescription;
use App\Core\Models\CrmStage;
use App\Core\Models\DentalLaboratory;
use App\Core\Models\Employee;
use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\NotificationLog;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\PatientCrmProfile;
use App\Core\Models\Payment;
use App\Core\Models\PaymentAllocation;
use App\Core\Models\Professional;
use App\Core\Models\Room;
use App\Core\Models\ScheduleBlock;
use App\Core\Models\Specialty;
use App\Core\Models\StockMovement;
use App\Core\Models\Warehouse;
use App\Core\Services\ClinicalIntegrityService;
use App\Platform\Tenancy\Models\ClinicProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ClinicDemoShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $patients = Patient::query()->whereIn('record_number', ['HC-00001', 'HC-00002', 'HC-00003'])->get()->keyBy('record_number');
        $carlos = $patients->get('HC-00001');
        $sofia = $patients->get('HC-00002');
        $elena = $patients->get('HC-00003');
        $doctor = Professional::query()->where('license_number', 'COL-ODON-8492')->first();
        $periodontist = Professional::query()->where('license_number', 'COL-ODON-9120')->first();
        $owner = User::query()->where('email', 'owner@bsdental.com')->first();

        if (! $carlos || ! $sofia || ! $elena || ! $doctor || ! $periodontist || ! $owner) {
            Log::warning('Faltan registros base del tenant demo; se omitieron los datos de muestra ampliados.');

            return;
        }

        ClinicProfile::query()->firstOrCreate(
            ['clinic_name' => 'BSolutions Dental Clinic & Specialties'],
            [
                'legal_name' => 'BSolutions Dental, C.A.',
                'trade_name' => 'BSDental',
                'tax_id' => 'J-40987654-3',
                'phone' => '+58 212 999-8801',
                'email' => 'contacto@bsdental.com',
                'currency' => 'USD',
                'timezone' => 'America/Caracas',
            ],
        );

        $rehabilitation = Specialty::query()->firstOrCreate(['code' => 'REHAB'], ['name' => 'Rehabilitación Oral', 'is_active' => true]);
        $periodontics = Specialty::query()->firstOrCreate(['code' => 'PERIO'], ['name' => 'Periodoncia e Implantología', 'is_active' => true]);
        $doctor->specialties()->syncWithoutDetaching([$rehabilitation->id]);
        $periodontist->specialties()->syncWithoutDetaching([$periodontics->id]);

        $encounter = ClinicalEncounter::query()->firstOrCreate(
            ['patient_id' => $carlos->id, 'chief_complaint' => 'Sensibilidad al masticar en pieza 16'],
            [
                'professional_id' => $doctor->id,
                'encounter_date' => now()->subDays(7),
                'physical_examination' => 'Restauración oclusal estable. Sensibilidad leve sin signos de compromiso pulpar.',
                'vital_signs' => ['blood_pressure' => '128/82', 'heart_rate' => 74, 'temperature' => 36.6, 'oxygen_saturation' => 98],
                'status' => 'draft',
            ],
        );
        ClinicalEvolution::query()->firstOrCreate(['encounter_id' => $encounter->id], [
            'subjective' => 'Paciente refiere sensibilidad leve al frío y al masticar desde hace tres días.',
            'objective' => 'Pieza 16 vital, restauración íntegra y contacto oclusal ligeramente alto.',
            'assessment' => 'Sensibilidad postoperatoria asociada a contacto prematuro.',
            'plan' => 'Ajuste oclusal, control de sensibilidad y reevaluación en siete días.',
            'treatment_performed' => 'Ajuste selectivo y pulido de restauración en pieza 16.',
            'recommendations' => 'Evitar alimentos muy fríos durante 48 horas y acudir si aparece dolor espontáneo.',
        ]);
        ClinicalDiagnosis::query()->firstOrCreate(['encounter_id' => $encounter->id, 'code' => 'K08.89'], [
            'description' => 'Sensibilidad dental postoperatoria localizada',
            'type' => 'definitive',
        ]);
        ClinicalPrescription::query()->firstOrCreate(['encounter_id' => $encounter->id, 'medication_name' => 'Ibuprofeno'], [
            'dosage' => '400 mg',
            'frequency' => 'Cada 8 horas si presenta dolor',
            'duration' => '2 días',
            'instructions' => 'Tomar después de alimentos. Suspender ante reacción adversa.',
        ]);
        if ($encounter->status === 'draft') {
            app(ClinicalIntegrityService::class)->finalize($encounter, $owner->id);
        }

        $this->seedReceivables($sofia, $elena, $doctor, $owner);
        $this->seedPayroll($doctor, $periodontist);
        $this->seedOperations($carlos, $elena, $doctor, $owner);
    }

    private function seedPayroll(Professional $doctor, Professional $periodontist): void
    {
        Employee::updateOrCreate(
            ['professional_id' => $doctor->id],
            [
                'employee_number' => 'EMP-DOC-001',
                'full_name' => $doctor->full_name,
                'position' => 'Odontólogo rehabilitador',
                'compensation_type' => 'commission',
                'monthly_salary' => 0,
                'commission_rate' => 35,
                'hire_date' => now()->subYears(2)->startOfYear(),
                'status' => 'active',
            ],
        );

        Employee::updateOrCreate(
            ['professional_id' => $periodontist->id],
            [
                'employee_number' => 'EMP-DOC-002',
                'full_name' => $periodontist->full_name,
                'position' => 'Periodoncista',
                'compensation_type' => 'commission',
                'monthly_salary' => 0,
                'commission_rate' => 40,
                'hire_date' => now()->subYear()->startOfYear(),
                'status' => 'active',
            ],
        );

        Employee::updateOrCreate(
            ['employee_number' => 'EMP-FIX-001'],
            [
                'professional_id' => null,
                'full_name' => 'Laura Salazar',
                'position' => 'Recepcionista',
                'compensation_type' => 'fixed_salary',
                'monthly_salary' => 32000,
                'commission_rate' => 0,
                'hire_date' => now()->subYears(2)->startOfYear(),
                'status' => 'active',
            ],
        );

        Employee::updateOrCreate(
            ['employee_number' => 'EMP-FIX-002'],
            [
                'professional_id' => null,
                'full_name' => 'Marcos Peña',
                'position' => 'Caja y cobranzas',
                'compensation_type' => 'fixed_salary',
                'monthly_salary' => 35000,
                'commission_rate' => 0,
                'hire_date' => now()->subYear()->startOfYear(),
                'status' => 'active',
            ],
        );
    }

    private function seedReceivables(Patient $sofia, Patient $elena, Professional $doctor, User $owner): void
    {
        $this->createAgedCharge($sofia, $doctor, $owner, 'CHG-DEMO-002', 'Profilaxis y evaluación preventiva', 50, 50, 'pending', 15);
        $partial = $this->createAgedCharge($elena, $doctor, $owner, 'CHG-DEMO-003', 'Evaluación periodontal integral', 120, 80, 'partially_paid', 45, 40);
        $this->createAgedCharge($sofia, $doctor, $owner, 'CHG-DEMO-004', 'Reserva de tratamiento estético', 300, 300, 'pending', 75);

        $payment = Payment::query()->firstOrCreate(['payment_number' => 'REC-DEMO-002'], [
            'patient_id' => $elena->id,
            'total_amount' => 40,
            'allocated_amount' => 40,
            'unallocated_amount' => 0,
            'refunded_amount' => 0,
            'status' => 'fully_allocated',
            'paid_at' => now()->subDays(40),
            'created_by_user_id' => $owner->id,
        ]);
        PaymentAllocation::query()->firstOrCreate(
            ['payment_id' => $payment->id, 'patient_charge_id' => $partial->id],
            ['amount' => 40, 'allocated_at' => now()->subDays(40)],
        );

    }

    private function createAgedCharge(Patient $patient, Professional $professional, User $owner, string $number, string $concept, float $total, float $balance, string $status, int $ageDays, float $paid = 0): PatientCharge
    {
        $charge = PatientCharge::query()->firstOrCreate(['charge_number' => $number], [
            'patient_id' => $patient->id,
            'professional_id' => $professional->id,
            'concept' => $concept,
            'amount' => $total,
            'tax_amount' => 0,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'status' => $status,
            'due_date' => now()->subDays(max(0, $ageDays - 30))->toDateString(),
            'created_by_user_id' => $owner->id,
        ]);

        if ($charge->wasRecentlyCreated) {
            $charge->forceFill(['created_at' => now()->subDays($ageDays), 'updated_at' => now()->subDays($ageDays)])->saveQuietly();
        }

        return $charge;
    }

    private function seedOperations(Patient $carlos, Patient $elena, Professional $doctor, User $owner): void
    {
        $branch = Branch::query()->where('is_main', true)->first();
        $room = Room::query()->where('code', 'SIL-02')->first();
        if ($branch && $room) {
            ScheduleBlock::query()->firstOrCreate(['branch_id' => $branch->id, 'title' => 'Mantenimiento preventivo del sillón 02'], [
                'professional_id' => $doctor->id,
                'room_id' => $room->id,
                'reason' => 'maintenance',
                'start_time' => now()->addDay()->setTime(12, 0),
                'end_time' => now()->addDay()->setTime(13, 0),
                'created_by_user_id' => $owner->id,
            ]);
        }

        $stage = CrmStage::query()->where('slug', 'valoracion-realizada')->first();
        if ($stage) {
            PatientCrmProfile::query()->firstOrCreate(['patient_id' => $elena->id], [
                'stage_id' => $stage->id,
                'source' => 'Referencia médica',
                'estimated_lifetime_value' => 420,
                'notes' => 'Interesada en tratamiento periodontal y rehabilitación posterior.',
            ]);
        }
        NotificationLog::query()->firstOrCreate(['provider_message_id' => 'demo-wa-delivered-001'], [
            'patient_id' => $carlos->id,
            'channel' => 'whatsapp',
            'recipient' => $carlos->phone,
            'status' => 'delivered',
            'content' => 'Hola Carlos, te recordamos tu control de la pieza 16. Responde SI para confirmar.',
            'scheduled_at' => now()->subDays(2),
            'sent_at' => now()->subDays(2),
        ]);
        NotificationLog::query()->firstOrCreate(['provider_message_id' => 'demo-wa-responded-002'], [
            'patient_id' => $elena->id,
            'channel' => 'whatsapp',
            'recipient' => $elena->phone,
            'status' => 'responded',
            'content' => 'Hola Elena, confirmamos tu valoración periodontal para mañana a las 2:00 p. m.',
            'scheduled_at' => now()->subDay(),
            'sent_at' => now()->subDay(),
        ]);

        $category = InventoryCategory::query()->where('name', 'Insumos de Operatoria Dental')->first();
        $warehouse = Warehouse::query()->where('is_main', true)->first();
        if ($category && $warehouse) {
            $item = InventoryItem::query()->firstOrCreate(['sku' => 'RES-NANO-A2'], [
                'category_id' => $category->id,
                'name' => 'Resina nanohíbrida tono A2',
                'unit' => 'jeringa',
                'min_stock' => 5,
                'cost_price' => 18.5,
                'is_active' => true,
            ]);
            $batch = InventoryBatch::query()->firstOrCreate(['inventory_item_id' => $item->id, 'batch_number' => 'RES-A2-DEMO'], [
                'warehouse_id' => $warehouse->id,
                'initial_quantity' => 8,
                'current_quantity' => 3,
                'cost_per_unit' => 18.5,
                'expires_at' => now()->addMonths(2),
            ]);
            StockMovement::query()->firstOrCreate(['inventory_item_id' => $item->id, 'batch_id' => $batch->id, 'type' => 'procedure_consumption'], [
                'warehouse_id' => $warehouse->id,
                'quantity' => 5,
                'previous_stock' => 8,
                'new_stock' => 3,
                'unit_cost' => 18.5,
                'total_cost' => 92.5,
                'notes' => 'Consumo acumulado de restauraciones demo',
                'created_by_user_id' => $owner->id,
                'created_at' => now()->subDays(3),
            ]);
        }

        $laboratory = DentalLaboratory::query()->first();
        if ($laboratory) {
            LabOrder::query()->firstOrCreate(['order_number' => 'LAB-DEMO-002'], [
                'laboratory_id' => $laboratory->id,
                'patient_id' => $elena->id,
                'tooth_number' => 11,
                'work_description' => 'Corona provisional en PMMA para pieza 11',
                'shade_guide' => 'A2',
                'status' => 'received',
                'sent_date' => now()->subDays(10),
                'due_date' => now()->subDays(3),
                'received_date' => now()->subDays(2),
                'estimated_cost' => 45,
                'final_cost' => 45,
                'payable_status' => 'paid',
                'notes' => 'Adaptación marginal y color verificados.',
                'quality_check_notes' => 'Aprobada sin ajustes; lista para prueba clínica.',
                'created_by_user_id' => $owner->id,
            ]);
        }
    }
}
