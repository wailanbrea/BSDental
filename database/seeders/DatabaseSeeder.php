<?php

namespace Database\Seeders;

use App\Core\Auth\Database\Seeders\TenantRbacSeeder;
use App\Core\Auth\Models\User;
use App\Core\Models\Appointment;
use App\Core\Models\AppointmentType;
use App\Core\Models\Branch;
use App\Core\Models\CashMovement;
use App\Core\Models\CashRegister;
use App\Core\Models\CashSession;
use App\Core\Models\ConsentTemplate;
use App\Core\Models\CrmStage;
use App\Core\Models\DentalLaboratory;
use App\Core\Models\FollowUpTask;
use App\Core\Models\InventoryBatch;
use App\Core\Models\InventoryCategory;
use App\Core\Models\InventoryItem;
use App\Core\Models\LabOrder;
use App\Core\Models\NotificationTemplate;
use App\Core\Models\Odontogram;
use App\Core\Models\OdontogramEntry;
use App\Core\Models\Patient;
use App\Core\Models\PatientCharge;
use App\Core\Models\PatientCrmProfile;
use App\Core\Models\PatientMedicalHistory;
use App\Core\Models\Payment;
use App\Core\Models\PaymentAllocation;
use App\Core\Models\PaymentSplit;
use App\Core\Models\Procedure;
use App\Core\Models\ProcedureCategory;
use App\Core\Models\Professional;
use App\Core\Models\ProfessionalCompensation;
use App\Core\Models\Quote;
use App\Core\Models\QuoteItem;
use App\Core\Models\Room;
use App\Core\Models\StockMovement;
use App\Core\Models\TreatmentPlan;
use App\Core\Models\TreatmentPlanItem;
use App\Core\Models\UserNotification;
use App\Core\Models\Warehouse;
use App\Platform\Auth\Models\PlatformUser;
use App\Platform\Plans\Models\Plan;
use App\Platform\Tenancy\Models\Tenant;
use App\Platform\Tenancy\Models\TenantDomain;
use App\Platform\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function __construct(
        private readonly ?string $tenantDatabasePath = null
    ) {}

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultTenantPath = database_path('tenant_demo.sqlite');
        $normalizedDefaultPath = str_replace('\\', '/', $defaultTenantPath);
        $normalizedRequestedPath = $this->tenantDatabasePath === null ? null : str_replace('\\', '/', $this->tenantDatabasePath);

        if (app()->environment('testing') && ($normalizedRequestedPath === null || strcasecmp($normalizedRequestedPath, $normalizedDefaultPath) === 0)) {
            throw new RuntimeException('DatabaseSeeder requires an explicit isolated tenant database path while testing.');
        }

        // 1. Seed Landlord Database
        $this->command->info('1. Migrando y sembrando Landlord Database...');
        Schema::connection('landlord')->dropAllTables();
        Artisan::call('migrate', [
            '--path' => 'database/migrations/landlord',
            '--database' => 'landlord',
            '--force' => true,
        ]);

        $planEnterprise = Plan::create([
            'name' => 'Plan Enterprise Multi-Sede',
            'description' => 'Acceso completo a todos los módulos clínicos, financieros, CRM y multi-sucursal.',
            'modules' => [
                'patients',
                'agenda',
                'clinical',
                'odontogram',
                'quotes',
                'inventory',
                'lab',
                'billing',
                'finance',
                'marketing',
                'analytics',
                'multi_branch',
            ],
            'max_users' => 50,
            'max_branches' => 10,
            'is_active' => true,
        ]);

        Plan::create([
            'name' => 'Plan Profesional',
            'description' => 'Para clínicas en crecimiento.',
            'modules' => ['clinical', 'billing', 'cash', 'inventory', 'crm', 'analytics'],
            'max_users' => 10,
            'max_branches' => 2,
            'is_active' => true,
        ]);

        PlatformUser::create([
            'name' => 'Super Administrador Platform',
            'email' => 'admin@bsdental.io',
            'password' => Hash::make('Password123!'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        $dbTenantPath = $this->tenantDatabasePath ?? $defaultTenantPath;
        if (file_exists($dbTenantPath)) {
            @unlink($dbTenantPath);
        }
        touch($dbTenantPath);

        $tenant = Tenant::create([
            'name' => 'BSolutions Dental Clinic & Specialties',
            'slug' => 'demo',
            'database_name' => $dbTenantPath,
            'status' => 'active',
            'plan_id' => $planEnterprise->id,
            'settings' => [
                'tax_id' => 'J-40918234-1',
                'currency' => 'USD',
                'timezone' => 'America/Caracas',
                'phone' => '+58 212 999-8800',
                'address' => 'Av. Principal de Las Mercedes, Torre BSolutions, Piso 8, Caracas',
            ],
        ]);

        $primaryDomain = strtolower(trim((string) config('multitenancy.demo_tenant_domain')));
        if ($primaryDomain === '') {
            throw new RuntimeException('The seeded demo tenant requires a primary domain.');
        }

        $domains = array_unique([
            $primaryDomain,
            'demo.localhost',
            'demo.lvh.me',
            'demo.127.0.0.1.nip.io',
            'demo.bsdental.test',
            'app.localhost',
        ]);

        foreach ($domains as $domain) {
            TenantDomain::create([
                'tenant_id' => $tenant->id,
                'domain' => $domain,
                'is_primary' => $domain === $primaryDomain,
                'is_verified' => true,
            ]);
        }

        // 2. Seed Tenant Database
        $this->command->info('2. Migrando y sembrando Tenant Demo Database...');
        $context = app(TenantContext::class);
        $context->execute($tenant, function () {
            Schema::connection('tenant')->dropAllTables();
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--database' => 'tenant',
                '--force' => true,
            ]);

            $this->call(TenantRbacSeeder::class);

            // Users
            $owner = User::create([
                'name' => 'Dra. Patricia Benítez (Directora)',
                'email' => 'owner@bsdental.com',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $owner->assignRole('Owner');

            $dentistUser = User::create([
                'name' => 'Dr. Alejandro Pérez (Rehabilitador)',
                'email' => 'doctor@bsdental.com',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $dentistUser->assignRole('GeneralDentist');

            $receptionUser = User::create([
                'name' => 'Laura Salazar (Recepción)',
                'email' => 'recepcion@bsdental.com',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $receptionUser->assignRole('Receptionist');

            $cashierUser = User::create([
                'name' => 'Marcos Peña (Caja y Cobranzas)',
                'email' => 'cajero@bsdental.com',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $cashierUser->assignRole('Cashier');

            $inventoryUser = User::create([
                'name' => 'Roberto Gil (Jefe de Almacén)',
                'email' => 'almacen@bsdental.com',
                'password' => Hash::make('Password123!'),
                'status' => 'active',
            ]);
            $inventoryUser->assignRole('InventoryManager');

            // Branches
            $branchMain = Branch::create([
                'name' => 'Sede Principal Las Mercedes',
                'address' => 'Av. Principal Las Mercedes, Torre BSolutions Piso 8',
                'phone' => '+58 212 999-8801',
                'is_main' => true,
                'is_active' => true,
            ]);

            $branchChacao = Branch::create([
                'name' => 'Sede Chacao',
                'address' => 'Calle Elice, Edif. San Carlos Piso 2',
                'phone' => '+58 212 999-8802',
                'is_main' => false,
                'is_active' => true,
            ]);

            // Rooms (Chairs)
            $room1 = Room::create(['branch_id' => $branchMain->id, 'name' => 'Sillón 01 — Cirugía e Implantes', 'code' => 'SIL-01', 'is_active' => true]);
            $room2 = Room::create(['branch_id' => $branchMain->id, 'name' => 'Sillón 02 — Rehabilitación & Estética', 'code' => 'SIL-02', 'is_active' => true]);
            $room3 = Room::create(['branch_id' => $branchChacao->id, 'name' => 'Sillón 03 — Ortodoncia & General', 'code' => 'SIL-03', 'is_active' => true]);

            // Professionals
            $prof1 = Professional::create([
                'user_id' => $dentistUser->id,
                'first_name' => 'Alejandro',
                'last_name' => 'Pérez',
                'license_number' => 'COL-ODON-8492',
                'color' => '#0d9488',
                'phone' => '+58 414 111-2233',
                'email' => 'dr.perez@bsdental.com',
                'is_active' => true,
            ]);
            $prof1->branches()->attach([$branchMain->id, $branchChacao->id]);

            $prof2 = Professional::create([
                'first_name' => 'Valeria',
                'last_name' => 'Gómez',
                'license_number' => 'COL-ODON-9120',
                'color' => '#6366f1',
                'phone' => '+58 412 333-4455',
                'email' => 'dra.gomez@bsdental.com',
                'is_active' => true,
            ]);
            $prof2->branches()->attach([$branchMain->id]);

            // Appointment Types
            $typeEval = AppointmentType::create(['name' => 'Primera Consulta / Valoración', 'duration_minutes' => 30, 'color' => '#0ea5e9', 'is_active' => true]);
            $typeTrt = AppointmentType::create(['name' => 'Tratamiento Operatorias / Resinas', 'duration_minutes' => 45, 'color' => '#10b981', 'is_active' => true]);
            $typeSurg = AppointmentType::create(['name' => 'Cirugía / Implante', 'duration_minutes' => 60, 'color' => '#f43f5e', 'is_active' => true]);

            // Procedures Catalog
            $catPreventiva = ProcedureCategory::create(['name' => 'Odontología Preventiva y Diagnóstico', 'color' => '#06b6d4']);
            $catOperatoria = ProcedureCategory::create(['name' => 'Operatoria y Estética Dental', 'color' => '#10b981']);
            $catImplantes = ProcedureCategory::create(['name' => 'Implantología y Cirugía', 'color' => '#f59e0b']);
            $catEndodoncia = ProcedureCategory::create(['name' => 'Endodoncia', 'color' => '#8b5cf6']);

            $procEval = Procedure::create(['category_id' => $catPreventiva->id, 'code' => 'DIAG-01', 'name' => 'Evaluación Diagnóstica Integral + Odontograma', 'price' => 35.00]);
            $procProf = Procedure::create(['category_id' => $catPreventiva->id, 'code' => 'PREV-01', 'name' => 'Profilaxis y Tartrectomía Ultrasónica', 'price' => 50.00]);
            $procResina = Procedure::create(['category_id' => $catOperatoria->id, 'code' => 'RES-01', 'name' => 'Restauración Resina Nanohíbrida 3M', 'price' => 65.00]);
            $procImplante = Procedure::create(['category_id' => $catImplantes->id, 'code' => 'IMP-01', 'name' => 'Implante Dental Titanio Grado Quirúrgico', 'price' => 650.00]);
            $procEndo = Procedure::create(['category_id' => $catEndodoncia->id, 'code' => 'ENDO-01', 'name' => 'Endodoncia Unirradicular Mecanizada', 'price' => 140.00]);

            // Consent Templates
            ConsentTemplate::create([
                'slug' => 'con-imp-01',
                'title' => 'Consentimiento Informado para Procedimientos de Cirugía e Implantes',
                'version' => 1,
                'content' => "## Declaración del Paciente\nPor medio del presente documento declaro haber sido informado sobre los riesgos, beneficios y alternativas terapéuticas del procedimiento de colocación de implantes dentales...",
                'is_active' => true,
            ]);

            // Patients
            $p1 = Patient::create([
                'record_number' => 'HC-00001',
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza Vivas',
                'identification_type' => 'CI',
                'identification_number' => '18945231',
                'phone' => '+58 412 888-1122',
                'email' => 'carlos.mendoza@email.com',
                'gender' => 'male',
                'birth_date' => '1988-05-14',
                'status' => 'active',
            ]);

            PatientMedicalHistory::create([
                'patient_id' => $p1->id,
                'allergies' => ['Penicilina', 'Sulfas'],
                'systemic_conditions' => ['Hipertensión controlada'],
                'current_medications' => ['Losartán 50mg/día'],
                'bleeding_disorders' => false,
                'medical_notes' => 'Paciente controlado por cardiólogo',
            ]);

            $p2 = Patient::create([
                'record_number' => 'HC-00002',
                'first_name' => 'Sofía',
                'last_name' => 'Rodríguez Paéz',
                'identification_type' => 'CI',
                'identification_number' => '24112980',
                'phone' => '+58 414 777-3344',
                'email' => 'sofia.rodriguez@email.com',
                'gender' => 'female',
                'birth_date' => '1995-11-20',
                'status' => 'active',
            ]);

            PatientMedicalHistory::create([
                'patient_id' => $p2->id,
                'allergies' => [],
                'systemic_conditions' => [],
                'current_medications' => [],
                'medical_notes' => 'Aparente buen estado de salud general',
            ]);

            $p3 = Patient::create([
                'record_number' => 'HC-00003',
                'first_name' => 'Elena',
                'last_name' => 'Morales de Silva',
                'identification_type' => 'CI',
                'identification_number' => '12450890',
                'phone' => '+58 424 555-9988',
                'email' => 'elena.morales@email.com',
                'gender' => 'female',
                'birth_date' => '1970-03-08',
                'status' => 'active',
            ]);

            // Odontogram entries for Carlos Mendoza (p1)
            $odo = Odontogram::create([
                'patient_id' => $p1->id,
                'type' => 'initial',
                'notes' => 'Odontograma inicial de valoración',
            ]);

            OdontogramEntry::create([
                'odontogram_id' => $odo->id,
                'tooth_number' => 16,
                'surface' => 'occlusal_incisal',
                'condition' => 'caries',
                'lifecycle_state' => 'initial_diagnosis',
                'notes' => 'Caries oclusal profunda sin compromiso pulpar',
                'recorded_at' => Carbon::now(),
            ]);

            OdontogramEntry::create([
                'odontogram_id' => $odo->id,
                'tooth_number' => 21,
                'surface' => 'vestibular',
                'condition' => 'restored_composite',
                'lifecycle_state' => 'completed',
                'notes' => 'Carilla en resina estética realizada en sesión previa',
                'recorded_at' => Carbon::now(),
            ]);

            OdontogramEntry::create([
                'odontogram_id' => $odo->id,
                'tooth_number' => 46,
                'surface' => 'occlusal_incisal',
                'condition' => 'missing',
                'lifecycle_state' => 'planned',
                'notes' => 'Edéntulo parcial para implante titanio',
                'recorded_at' => Carbon::now(),
            ]);

            $this->call(PeriodontalDemoSeeder::class);

            // Quote and Treatment Plan for Carlos Mendoza
            $quote = Quote::create([
                'patient_id' => $p1->id,
                'quote_number' => 'PRE-00001',
                'alternative_name' => 'Plan A — Rehabilitación Integral',
                'subtotal' => 765.00,
                'discount_total' => 38.25,
                'tax_total' => 0.00,
                'grand_total' => 726.75,
                'status' => 'approved',
                'approved_at' => Carbon::now()->subDays(2),
            ]);

            $qi1 = QuoteItem::create([
                'quote_id' => $quote->id,
                'procedure_id' => $procResina->id,
                'tooth_number' => 16,
                'surface' => 'occlusal_incisal',
                'quantity' => 1,
                'unit_price' => 65.00,
                'subtotal' => 65.00,
                'total' => 65.00,
                'is_approved' => true,
            ]);

            $qi2 = QuoteItem::create([
                'quote_id' => $quote->id,
                'procedure_id' => $procImplante->id,
                'tooth_number' => 46,
                'quantity' => 1,
                'unit_price' => 650.00,
                'subtotal' => 650.00,
                'total' => 650.00,
                'is_approved' => true,
            ]);

            $plan = TreatmentPlan::create([
                'patient_id' => $p1->id,
                'quote_id' => $quote->id,
                'title' => 'Plan de Rehabilitación Integral',
                'total_estimated' => 726.75,
                'total_performed' => 65.00,
                'progress_percentage' => 50.00,
                'status' => 'in_progress',
            ]);

            $tpi1 = TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id,
                'procedure_id' => $procResina->id,
                'tooth_number' => 16,
                'phase' => 1,
                'price' => 65.00,
                'status' => 'completed',
                'completed_at' => Carbon::now()->subDays(1),
            ]);

            TreatmentPlanItem::create([
                'treatment_plan_id' => $plan->id,
                'procedure_id' => $procImplante->id,
                'tooth_number' => 46,
                'phase' => 2,
                'price' => 650.00,
                'status' => 'pending',
            ]);

            // Appointments for today and week
            $today = Carbon::today();

            Appointment::create([
                'patient_id' => $p1->id,
                'professional_id' => $prof1->id,
                'branch_id' => $branchMain->id,
                'room_id' => $room1->id,
                'appointment_type_id' => $typeTrt->id,
                'start_time' => $today->copy()->setTime(9, 0),
                'end_time' => $today->copy()->setTime(9, 45),
                'duration_minutes' => 45,
                'status' => 'completed',
                'reason' => 'Restauración Resina Oclusal #16',
            ]);

            Appointment::create([
                'patient_id' => $p2->id,
                'professional_id' => $prof1->id,
                'branch_id' => $branchMain->id,
                'room_id' => $room2->id,
                'appointment_type_id' => $typeEval->id,
                'start_time' => $today->copy()->setTime(10, 30),
                'end_time' => $today->copy()->setTime(11, 0),
                'duration_minutes' => 30,
                'status' => 'confirmed',
                'reason' => 'Valoración General y Limpieza Ultrasónica',
            ]);

            Appointment::create([
                'patient_id' => $p3->id,
                'professional_id' => $prof2->id,
                'branch_id' => $branchMain->id,
                'room_id' => $room1->id,
                'appointment_type_id' => $typeSurg->id,
                'start_time' => $today->copy()->setTime(14, 0),
                'end_time' => $today->copy()->setTime(15, 0),
                'duration_minutes' => 60,
                'status' => 'scheduled',
                'reason' => 'Evaluación Quirúrgica Periodontal',
            ]);

            // Inventory & Labs
            $catMat = InventoryCategory::create(['name' => 'Biomateriales e Implantes']);
            $catIns = InventoryCategory::create(['name' => 'Insumos de Operatoria Dental']);

            $wh = Warehouse::create([
                'branch_id' => $branchMain->id,
                'name' => 'Almacén Central Las Mercedes',
                'is_main' => true,
            ]);

            $itemTitanium = InventoryItem::create([
                'category_id' => $catMat->id,
                'sku' => 'IMP-TIT-375',
                'name' => 'Implante Titanio 3.75 x 11.5mm',
                'unit' => 'unidad',
                'min_stock' => 5.0,
                'cost_price' => 120.00,
            ]);

            $batch = InventoryBatch::create([
                'inventory_item_id' => $itemTitanium->id,
                'warehouse_id' => $wh->id,
                'batch_number' => 'LOT-2026-08',
                'initial_quantity' => 20.0,
                'current_quantity' => 19.0,
                'cost_per_unit' => 120.00,
                'expires_at' => Carbon::now()->addYears(3),
            ]);

            StockMovement::create([
                'inventory_item_id' => $itemTitanium->id,
                'warehouse_id' => $wh->id,
                'batch_id' => $batch->id,
                'type' => 'purchase',
                'quantity' => 20.0,
                'previous_stock' => 0.0,
                'new_stock' => 20.0,
                'unit_cost' => 120.00,
                'total_cost' => 2400.00,
                'notes' => 'FAC-PROV-9912',
                'created_at' => Carbon::now(),
            ]);

            StockMovement::create([
                'inventory_item_id' => $itemTitanium->id,
                'warehouse_id' => $wh->id,
                'batch_id' => $batch->id,
                'type' => 'consumption',
                'quantity' => 1.0,
                'previous_stock' => 20.0,
                'new_stock' => 19.0,
                'unit_cost' => 120.00,
                'total_cost' => 120.00,
                'notes' => 'Consumo Cirugía #46',
                'created_at' => Carbon::now(),
            ]);

            $dentalLab = DentalLaboratory::create([
                'name' => 'Laboratorio Dental Estética 3D',
                'contact_person' => 'Téc. Roberto Valdivia',
                'phone' => '+58 212 777-1199',
                'email' => 'pedidos@labestetica3d.com',
                'address' => 'Chacao, Torre Credicard Piso 4',
                'is_active' => true,
            ]);

            LabOrder::create([
                'laboratory_id' => $dentalLab->id,
                'patient_id' => $p1->id,
                'order_number' => 'LAB-00001',
                'work_description' => 'Corona Zirconio Monolítico #46',
                'shade_guide' => 'A2 Bleach',
                'status' => 'sent',
                'estimated_cost' => 85.00,
                'final_cost' => 85.00,
                'sent_date' => Carbon::now()->subDays(1),
                'due_date' => Carbon::now()->addDays(5),
            ]);

            // Cash & Invoicing
            $cashReg = CashRegister::create([
                'branch_id' => $branchMain->id,
                'name' => 'Caja Principal Recepción',
                'is_active' => true,
            ]);

            $session = CashSession::create([
                'cash_register_id' => $cashReg->id,
                'opened_by_user_id' => $receptionUser->id,
                'opening_balance' => 100.00,
                'expected_cash' => 100.00,
                'status' => 'open',
                'opened_at' => Carbon::now()->setTime(8, 0),
            ]);

            $charge1 = PatientCharge::create([
                'patient_id' => $p1->id,
                'treatment_plan_item_id' => $tpi1->id,
                'professional_id' => $prof1->id,
                'charge_number' => 'CHG-00001',
                'concept' => 'Restauración Resina Nanohíbrida 3M #16',
                'amount' => 65.00,
                'tax_amount' => 0.00,
                'total_amount' => 65.00,
                'paid_amount' => 65.00,
                'balance_due' => 0.00,
                'status' => 'paid',
            ]);

            $payment = Payment::create([
                'patient_id' => $p1->id,
                'cash_session_id' => $session->id,
                'created_by_user_id' => $receptionUser->id,
                'payment_number' => 'REC-00001',
                'total_amount' => 65.00,
                'allocated_amount' => 65.00,
                'unallocated_amount' => 0.00,
                'status' => 'completed',
                'paid_at' => Carbon::now(),
            ]);

            PaymentSplit::create([
                'payment_id' => $payment->id,
                'method' => 'card',
                'amount' => 65.00,
                'reference_code' => 'POS-992182',
            ]);

            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'patient_charge_id' => $charge1->id,
                'amount' => 65.00,
                'allocated_at' => Carbon::now(),
            ]);

            CashMovement::create([
                'cash_session_id' => $session->id,
                'created_by_user_id' => $receptionUser->id,
                'type' => 'income',
                'concept' => 'Cobro Recibo REC-00001 (Carlos Mendoza)',
                'amount' => 65.00,
                'payment_method' => 'card',
                'created_at' => Carbon::now(),
            ]);

            ProfessionalCompensation::create([
                'professional_id' => $prof1->id,
                'patient_charge_id' => $charge1->id,
                'rule_type' => 'percentage_production',
                'rate' => 35.00,
                'base_amount' => 65.00,
                'commission_amount' => 22.75,
                'status' => 'accrued',
                'accrued_at' => Carbon::now(),
            ]);

            // CRM & Follow-up
            FollowUpTask::create([
                'patient_id' => $p1->id,
                'type' => 'post_op',
                'priority' => 'high',
                'title' => 'Control Post-Operatorio Resina #16',
                'notes' => 'Llamar al paciente para verificar oclusión y sensibilidad tras restauración',
                'due_date' => Carbon::now()->addDay(),
                'status' => 'pending',
            ]);

            FollowUpTask::create([
                'patient_id' => $p2->id,
                'type' => 'quote_pending',
                'priority' => 'medium',
                'title' => 'Seguimiento Presupuesto Estética Dental',
                'notes' => 'Contactar para resolver dudas sobre el plan de blanqueamiento y carillas',
                'due_date' => Carbon::now()->addDays(3),
                'status' => 'pending',
            ]);

            NotificationTemplate::create([
                'name' => 'Recordatorio Cita 24h WhatsApp',
                'trigger_event' => 'appointment_reminder_24h',
                'channel' => 'whatsapp',
                'body_template' => 'Hola {{patient_name}}, te recordamos tu cita mañana a las {{appointment_time}} en BSDental con el {{doctor_name}}. ¿Confirmas tu asistencia? Responde SI para confirmar.',
                'is_active' => true,
            ]);

            UserNotification::create([
                'user_id' => $owner->id,
                'type' => 'inventory',
                'severity' => 'critical',
                'title' => 'Stock crítico en almacén',
                'message' => 'La resina fotocurable está por debajo del nivel mínimo configurado.',
                'action_url' => '/inventory',
                'data' => ['source' => 'demo_seed'],
            ]);

            UserNotification::create([
                'user_id' => $owner->id,
                'type' => 'follow_up',
                'severity' => 'warning',
                'title' => 'Seguimiento próximo a vencer',
                'message' => 'El control postoperatorio de Carlos Mendoza vence mañana.',
                'action_url' => '/crm',
                'data' => ['source' => 'demo_seed'],
            ]);

            UserNotification::create([
                'user_id' => $owner->id,
                'type' => 'appointment',
                'severity' => 'info',
                'title' => 'Agenda clínica actualizada',
                'message' => 'Hay citas pendientes de confirmación para las próximas 48 horas.',
                'action_url' => '/appointments',
                'data' => ['source' => 'demo_seed'],
                'read_at' => now(),
            ]);

            $stageNew = CrmStage::create(['name' => 'Lead / Paciente Nuevo', 'slug' => 'lead-nuevo', 'color' => '#06b6d4', 'order_index' => 1, 'is_active' => true]);
            $stageVal = CrmStage::create(['name' => 'Valoración Realizada', 'slug' => 'valoracion-realizada', 'color' => '#3b82f6', 'order_index' => 2, 'is_active' => true]);
            $stageTrt = CrmStage::create(['name' => 'En Tratamiento Activo', 'slug' => 'en-tratamiento', 'color' => '#10b981', 'order_index' => 3, 'is_active' => true]);

            PatientCrmProfile::create([
                'patient_id' => $p1->id,
                'stage_id' => $stageTrt->id,
                'source' => 'Recomendación Familiar',
                'estimated_lifetime_value' => 765.00,
            ]);

            PatientCrmProfile::create([
                'patient_id' => $p2->id,
                'stage_id' => $stageVal->id,
                'source' => 'Instagram ADS',
                'estimated_lifetime_value' => 50.00,
            ]);

            $this->call(ClinicDemoShowcaseSeeder::class);
        });

        $this->command->info('¡Sembrado de BSDental completado con éxito!');
    }
}
