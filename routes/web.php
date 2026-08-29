<?php

use App\Core\Auth\Controllers\TenantAuthController;
use App\Core\Auth\Controllers\TenantPasswordResetController;
use App\Core\Auth\Middleware\RequireActiveTenantUser;
use App\Core\Auth\Middleware\RequireTenantTwoFactor;
use App\Core\Controllers\AnalyticsDashboardController;
use App\Core\Controllers\AppointmentController;
use App\Core\Controllers\BillingController;
use App\Core\Controllers\BranchController;
use App\Core\Controllers\CashRegisterController;
use App\Core\Controllers\ClinicalEncounterController;
use App\Core\Controllers\ClinicalPlanController;
use App\Core\Controllers\ClinicDashboardController;
use App\Core\Controllers\ClinicSettingsController;
use App\Core\Controllers\ClinicUserController;
use App\Core\Controllers\ConsentController;
use App\Core\Controllers\CrmFollowUpController;
use App\Core\Controllers\DentalLabController;
use App\Core\Controllers\InventoryController;
use App\Core\Controllers\NotificationCenterController;
use App\Core\Controllers\OdontogramController;
use App\Core\Controllers\PatientController;
use App\Core\Controllers\PayrollController;
use App\Core\Controllers\ProcedureCatalogController;
use App\Core\Controllers\ProfessionalController;
use App\Core\Controllers\QuoteController;
use App\Core\Controllers\RoomController;
use App\Core\Controllers\TreatmentPlanController;
use App\Core\Controllers\WhatsAppWebhookController;
use App\Platform\Auth\Controllers\PlatformAuthController;
use App\Platform\Auth\Middleware\RequirePlatformAdmin;
use App\Platform\Auth\Middleware\RequirePlatformTwoFactor;
use App\Platform\Controllers\PlatformDashboardController;
use App\Platform\Controllers\PlatformOperationsController;
use App\Platform\Controllers\PlatformTenantController;
use App\Platform\Tenancy\Middleware\EnsureModuleEntitlement;
use App\Platform\Tenancy\Middleware\PreventCentralDomainFromAccessingTenantDb;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'BSDental'),
        'version' => '4.0.0-dev',
        'phpVersion' => PHP_VERSION,
        'laravelVersion' => app()->version(),
        'environment' => app()->environment(),
    ]);
});

// Platform Admin Routes (Landlord Central Plane)
Route::prefix('platform')->name('platform.')->middleware([
    PreventCentralDomainFromAccessingTenantDb::class,
])->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('/login', [PlatformAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PlatformAuthController::class, 'login']);
    });

    Route::middleware(['auth:platform', RequirePlatformAdmin::class])->group(function () {
        Route::get('/two-factor', [PlatformAuthController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('/two-factor', [PlatformAuthController::class, 'verifyTwoFactor']);
        Route::post('/logout', [PlatformAuthController::class, 'logout'])->name('logout');

        Route::middleware([RequirePlatformTwoFactor::class])->group(function () {
            Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');
            Route::get('/tenants', [PlatformTenantController::class, 'index'])->name('tenants.index');
            Route::get('/tenants/create', [PlatformTenantController::class, 'create'])->name('tenants.create');
            Route::post('/tenants', [PlatformTenantController::class, 'store'])->name('tenants.store');
            Route::get('/tenants/{id}', [PlatformTenantController::class, 'show'])->name('tenants.show');
            Route::post('/tenants/{id}/suspend', [PlatformTenantController::class, 'suspend'])->name('tenants.suspend');
            Route::post('/tenants/{id}/resume', [PlatformTenantController::class, 'resume'])->name('tenants.resume');
            Route::post('/tenants/{id}/backup', [PlatformOperationsController::class, 'triggerBackup'])->name('tenants.backup');
            Route::post('/tenants/{id}/plan', [PlatformOperationsController::class, 'updatePlan'])->name('tenants.update_plan');
            Route::get('/operations', [PlatformOperationsController::class, 'dashboard'])->name('operations.dashboard');
        });
    });
});

// Tenant Routes (Clinic Tenant Plane)
Route::group([], function () {
    Route::middleware('guest:web')->group(function () {
        Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [TenantAuthController::class, 'login']);
        Route::get('/forgot-password', [TenantPasswordResetController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [TenantPasswordResetController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');
        Route::get('/reset-password/{token}', [TenantPasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('/reset-password', [TenantPasswordResetController::class, 'update'])->middleware('throttle:password-reset')->name('password.update');
    });

    Route::middleware(['auth:web', RequireActiveTenantUser::class])->group(function () {
        Route::get('/two-factor', [TenantAuthController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('/two-factor', [TenantAuthController::class, 'verifyTwoFactor']);
        Route::post('/logout', [TenantAuthController::class, 'logout'])->name('logout');

        Route::middleware([RequireTenantTwoFactor::class, 'branch.access'])->group(function () {
            Route::get('/dashboard', [ClinicDashboardController::class, 'index'])->name('clinic.dashboard');
            Route::get('/session/keep-alive', static fn () => response()->noContent())->name('clinic.session.keep_alive');

            Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('clinic.notifications.index');
            Route::patch('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('clinic.notifications.read_all');
            Route::patch('/notifications/{id}/read', [NotificationCenterController::class, 'markRead'])->name('clinic.notifications.read');

            // Clinic Core Management
            Route::get('/settings', [ClinicSettingsController::class, 'edit'])->middleware('permission:settings.view')->name('clinic.settings');
            Route::put('/settings', [ClinicSettingsController::class, 'update'])->middleware('permission:settings.update');

            Route::get('/users', [ClinicUserController::class, 'index'])->middleware('permission:users.view')->name('clinic.users.index');
            Route::post('/users', [ClinicUserController::class, 'store'])->middleware('permission:users.manage')->name('clinic.users.store');
            Route::put('/users/{id}', [ClinicUserController::class, 'update'])->middleware('permission:users.manage')->name('clinic.users.update');
            Route::put('/roles/{id}/permissions', [ClinicUserController::class, 'updateRole'])->middleware('permission:users.manage')->name('clinic.roles.update_permissions');

            Route::get('/branches', [BranchController::class, 'index'])->middleware('permission:settings.view')->name('clinic.branches');
            Route::post('/branches', [BranchController::class, 'store'])->middleware('permission:settings.update');
            Route::put('/branches/{branch}', [BranchController::class, 'update'])->middleware('permission:settings.update');
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:settings.update');

            Route::post('/branches/{branch}/rooms', [RoomController::class, 'store'])->middleware('permission:settings.update');
            Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->middleware('permission:settings.update');

            Route::get('/professionals', [ProfessionalController::class, 'index'])->middleware('permission:settings.view')->name('clinic.professionals');
            Route::post('/professionals', [ProfessionalController::class, 'store'])->middleware('permission:settings.update');
            Route::put('/professionals/{professional}', [ProfessionalController::class, 'update'])->middleware('permission:settings.update');
            Route::delete('/professionals/{professional}', [ProfessionalController::class, 'destroy'])->middleware('permission:settings.update');

            // Patients & Clinical Records
            Route::get('/patients', [PatientController::class, 'index'])->middleware('permission:patients.view')->name('clinic.patients.index');
            Route::get('/patients/create', [PatientController::class, 'create'])->middleware('permission:patients.create')->name('clinic.patients.create');
            Route::get('/patients/check-duplicates', [PatientController::class, 'checkDuplicates'])->middleware('permission:patients.create')->name('clinic.patients.check_duplicates');
            Route::post('/patients', [PatientController::class, 'store'])->middleware('permission:patients.create')->name('clinic.patients.store');
            Route::get('/patients/{id}/edit', [PatientController::class, 'edit'])->middleware('permission:patients.update')->name('clinic.patients.edit');
            Route::get('/patients/{id}', [PatientController::class, 'show'])->middleware('permission:patients.view')->name('clinic.patients.show');
            Route::put('/patients/{id}', [PatientController::class, 'update'])->middleware('permission:patients.update')->name('clinic.patients.update');
            Route::post('/patients/{id}/files', [PatientController::class, 'uploadFile'])->middleware('permission:patients.update')->name('clinic.patients.upload_file');
            Route::post('/patients/{id}/merge', [PatientController::class, 'merge'])->middleware('permission:patients.update')->name('clinic.patients.merge');
            Route::get('/patient-files/{id}/view', [PatientController::class, 'viewFile'])->middleware('permission:patients.view')->name('clinic.patient_files.view');
            Route::get('/patient-files/{id}/download', [PatientController::class, 'downloadFile'])->middleware('permission:patients.view')->name('clinic.patient_files.download');
            Route::delete('/patients/{id}', [PatientController::class, 'destroy'])->middleware('permission:patients.delete')->name('clinic.patients.destroy');

            // Agenda & Appointments
            Route::get('/appointments', [AppointmentController::class, 'index'])->middleware('permission:appointments.view')->name('clinic.appointments.index');
            Route::post('/appointments', [AppointmentController::class, 'store'])->middleware('permission:appointments.create')->name('clinic.appointments.store');
            Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->middleware('permission:appointments.update|appointments.cancel')->name('clinic.appointments.update_status');
            Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->middleware('permission:appointments.update')->name('clinic.appointments.reschedule');
            Route::post('/appointments/blocks', [AppointmentController::class, 'createBlock'])->middleware('permission:appointments.update')->name('clinic.appointments.create_block');

            // Clinical Encounters & Medical Records (Inmutable & Amendments)
            Route::get('/encounters', [ClinicalEncounterController::class, 'index'])->middleware('permission:clinical.view')->name('clinic.encounters.all');
            Route::get('/patients/{patientId}/encounters', [ClinicalEncounterController::class, 'index'])->middleware('permission:clinical.view')->name('clinic.encounters.index');
            Route::get('/patients/{patientId}/encounters/create', [ClinicalEncounterController::class, 'create'])->middleware('permission:clinical.write')->name('clinic.encounters.create');
            Route::post('/encounters', [ClinicalEncounterController::class, 'store'])->middleware('permission:clinical.write')->name('clinic.encounters.store');
            Route::get('/encounters/{id}', [ClinicalEncounterController::class, 'show'])->middleware('permission:clinical.view')->name('clinic.encounters.show');
            Route::put('/encounters/{id}', [ClinicalEncounterController::class, 'update'])->middleware('permission:clinical.write')->name('clinic.encounters.update');
            Route::post('/encounters/{id}/finalize', [ClinicalEncounterController::class, 'finalize'])->middleware('permission:clinical.finalize')->name('clinic.encounters.finalize');
            Route::post('/encounters/{id}/amend', [ClinicalEncounterController::class, 'amend'])->middleware('permission:clinical.finalize')->name('clinic.encounters.amend');

            // Clinical plans are proposals; they become executable only through quote approval.
            Route::get('/encounters/{encounterId}/clinical-plans/create', [ClinicalPlanController::class, 'create'])->middleware('permission:clinical.write')->name('clinic.clinical_plans.create');
            Route::post('/encounters/{encounterId}/clinical-plans', [ClinicalPlanController::class, 'store'])->middleware('permission:clinical.write')->name('clinic.clinical_plans.store');
            Route::get('/clinical-plans/{id}', [ClinicalPlanController::class, 'show'])->middleware('permission:clinical.view')->name('clinic.clinical_plans.show');
            Route::post('/clinical-plans/{id}/quotes', [ClinicalPlanController::class, 'convertToQuote'])->middleware('permission:quotes.create')->name('clinic.clinical_plans.convert_to_quote');

            // Odontogram FDI
            Route::get('/patients/{patientId}/odontogram', [OdontogramController::class, 'show'])->middleware('permission:odontogram.view')->name('clinic.odontogram.show');
            Route::post('/patients/{patientId}/odontogram/entries', [OdontogramController::class, 'storeEntry'])->middleware('permission:odontogram.write')->name('clinic.odontogram.store_entry');
            Route::post('/patients/{patientId}/odontogram/periodontal-measurements', [OdontogramController::class, 'storePeriodontalMeasurements'])->middleware('permission:odontogram.write')->name('clinic.odontogram.periodontal_measurements');
            Route::post('/patients/{patientId}/odontogram/caries-risk', [OdontogramController::class, 'storeCariesRisk'])->middleware('permission:odontogram.write')->name('clinic.odontogram.caries_risk');

            // Informed Consents
            Route::get('/patients/{patientId}/consents', [ConsentController::class, 'index'])->middleware('permission:clinical.view')->name('clinic.consents.index');
            Route::post('/patients/{patientId}/consents', [ConsentController::class, 'store'])->middleware('permission:clinical.write')->name('clinic.consents.store');

            // Procedures Catalog & Pricing
            Route::get('/procedures', [ProcedureCatalogController::class, 'index'])->middleware('permission:quotes.view|settings.view')->name('clinic.procedures.index');
            Route::post('/procedures', [ProcedureCatalogController::class, 'store'])->middleware('permission:settings.update')->name('clinic.procedures.store');

            // Quotes / Presupuestos
            Route::get('/quotes', [QuoteController::class, 'allIndex'])->middleware('permission:quotes.view')->name('clinic.quotes.all');
            Route::get('/quotes/quick-create', [QuoteController::class, 'quickCreate'])->middleware('permission:quotes.create')->name('clinic.quotes.quick_create');
            Route::post('/quotes/quick', [QuoteController::class, 'storeQuick'])->middleware('permission:quotes.create')->name('clinic.quotes.store_quick');
            Route::get('/patients/{patientId}/quotes', [QuoteController::class, 'index'])->middleware('permission:quotes.view')->name('clinic.quotes.index');
            Route::get('/patients/{patientId}/quotes/create', [QuoteController::class, 'create'])->middleware('permission:quotes.create')->name('clinic.quotes.create');
            Route::post('/patients/{patientId}/quotes', [QuoteController::class, 'store'])->middleware('permission:quotes.create')->name('clinic.quotes.store');
            Route::get('/quotes/{id}', [QuoteController::class, 'show'])->middleware('permission:quotes.view')->name('clinic.quotes.show');
            Route::post('/quotes/{id}/convert-to-patient', [QuoteController::class, 'convertToPatient'])->middleware('permission:quotes.create')->name('clinic.quotes.convert_to_patient');
            Route::post('/quotes/{id}/approve', [QuoteController::class, 'approve'])->middleware('permission:quotes.approve')->name('clinic.quotes.approve');
            Route::post('/quotes/{id}/reject', [QuoteController::class, 'reject'])->middleware('permission:quotes.approve')->name('clinic.quotes.reject');

            // Treatment Plans
            Route::get('/patients/{patientId}/treatment-plans', [TreatmentPlanController::class, 'index'])->middleware('permission:quotes.view')->name('clinic.treatment_plans.index');
            Route::get('/treatment-plans/{id}', [TreatmentPlanController::class, 'show'])->middleware('permission:quotes.view')->name('clinic.treatment_plans.show');
            Route::post('/treatment-items/{itemId}/complete', [TreatmentPlanController::class, 'completeItem'])->middleware('permission:clinical.write')->name('clinic.treatment_items.complete');

            // Inventory & Stock Ledger
            Route::get('/inventory', [InventoryController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':inventory', 'permission:inventory.view'])->name('clinic.inventory.index');
            Route::get('/inventory/items/{id}/kardex', [InventoryController::class, 'kardex'])->middleware([EnsureModuleEntitlement::class.':inventory', 'permission:inventory.view'])->name('clinic.inventory.kardex');
            Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->middleware([EnsureModuleEntitlement::class.':inventory', 'permission:inventory.adjust'])->name('clinic.inventory.store_item');
            Route::post('/inventory/purchases', [InventoryController::class, 'recordPurchase'])->middleware([EnsureModuleEntitlement::class.':inventory', 'permission:inventory.purchase'])->name('clinic.inventory.record_purchase');
            Route::post('/inventory/adjustments', [InventoryController::class, 'recordAdjustment'])->middleware([EnsureModuleEntitlement::class.':inventory', 'permission:inventory.adjust'])->name('clinic.inventory.record_adjustment');

            // Dental Laboratory & Prosthesis
            Route::get('/lab', [DentalLabController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':lab', 'permission:lab.view'])->name('clinic.lab.index');
            Route::post('/lab/orders', [DentalLabController::class, 'storeOrder'])->middleware([EnsureModuleEntitlement::class.':lab', 'permission:lab.order'])->name('clinic.lab.store_order');
            Route::post('/lab/orders/{id}/status', [DentalLabController::class, 'updateStatus'])->middleware([EnsureModuleEntitlement::class.':lab', 'permission:lab.receive'])->name('clinic.lab.update_status');
            Route::post('/lab/orders/{id}/quality', [DentalLabController::class, 'receiveQuality'])->middleware([EnsureModuleEntitlement::class.':lab', 'permission:lab.receive'])->name('clinic.lab.receive_quality');
            Route::post('/lab/orders/{id}/remake', [DentalLabController::class, 'remake'])->middleware([EnsureModuleEntitlement::class.':lab', 'permission:lab.order'])->name('clinic.lab.remake');

            // Patient Billing & Invoicing
            Route::get('/patients/{patientId}/billing', [BillingController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.view|finance.view'])->name('clinic.billing.index');
            Route::get('/patients/{patientId}/billing/statement', [BillingController::class, 'showStatement'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.view|finance.view'])->name('clinic.billing.statement');
            Route::post('/patients/{patientId}/billing/charges', [BillingController::class, 'storeCharge'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.create'])->name('clinic.billing.store_charge');
            Route::post('/patients/{patientId}/billing/payments', [BillingController::class, 'storePayment'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.create'])->name('clinic.billing.store_payment');
            Route::get('/charges/{chargeId}', [BillingController::class, 'showCharge'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.view|finance.view'])->name('clinic.billing.charge');
            Route::post('/charges/{chargeId}/adjustments', [BillingController::class, 'storeAdjustment'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.create'])->name('clinic.billing.adjustment');
            Route::get('/payments/{paymentId}', [BillingController::class, 'showPayment'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.view|finance.view'])->name('clinic.billing.payment');
            Route::post('/payments/{paymentId}/allocate', [BillingController::class, 'allocate'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.allocate'])->name('clinic.billing.allocate');
            Route::post('/payments/{paymentId}/refund', [BillingController::class, 'refund'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:payments.refund'])->name('clinic.billing.refund');
            Route::get('/billing/aging-receivables', [BillingController::class, 'agingReport'])->middleware([EnsureModuleEntitlement::class.':billing', 'permission:finance.reports'])->name('clinic.billing.aging');

            // Payroll & Professional Commissions
            Route::get('/payroll', [PayrollController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:finance.reports'])->name('clinic.payroll.index');
            Route::post('/payroll/employees', [PayrollController::class, 'storeEmployee'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:finance.reports'])->name('clinic.payroll.employees.store');
            Route::put('/payroll/employees/{employee}', [PayrollController::class, 'updateEmployee'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:finance.reports'])->name('clinic.payroll.employees.update');
            Route::post('/payroll/runs', [PayrollController::class, 'storeRun'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:finance.reports'])->name('clinic.payroll.runs.store');
            Route::post('/payroll/runs/{run}/pay', [PayrollController::class, 'payRun'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:finance.reports'])->name('clinic.payroll.runs.pay');

            // Cash Registers & Sessions
            Route::get('/cash-registers', [CashRegisterController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.view'])->name('clinic.cash.index');
            Route::post('/cash-registers/{id}/open', [CashRegisterController::class, 'open'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.open'])->name('clinic.cash.open');
            Route::get('/cash-sessions/{id}', [CashRegisterController::class, 'showSession'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.view'])->name('clinic.cash.session');
            Route::get('/cash-sessions/{id}/export', [CashRegisterController::class, 'exportSession'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.view|finance.reports'])->name('clinic.cash.session_export');
            Route::post('/cash-sessions/{id}/movements', [CashRegisterController::class, 'storeMovement'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.open|cash.close'])->name('clinic.cash.movement');
            Route::post('/cash-sessions/{id}/close', [CashRegisterController::class, 'close'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.close'])->name('clinic.cash.close');
            Route::post('/cash-sessions/{id}/reopen', [CashRegisterController::class, 'reopen'])->middleware([EnsureModuleEntitlement::class.':finance', 'permission:cash.reopen'])->name('clinic.cash.reopen');

            // CRM, Recalls & Follow-up
            Route::get('/crm', [CrmFollowUpController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':marketing', 'permission:crm.view'])->name('clinic.crm.index');
            Route::post('/crm/tasks', [CrmFollowUpController::class, 'storeTask'])->middleware([EnsureModuleEntitlement::class.':marketing', 'permission:crm.manage'])->name('clinic.crm.store_task');
            Route::post('/crm/tasks/{id}/complete', [CrmFollowUpController::class, 'completeTask'])->middleware([EnsureModuleEntitlement::class.':marketing', 'permission:crm.manage'])->name('clinic.crm.complete_task');
            Route::post('/crm/profiles/{id}/stage', [CrmFollowUpController::class, 'updateStage'])->middleware([EnsureModuleEntitlement::class.':marketing', 'permission:crm.manage'])->name('clinic.crm.update_stage');
            Route::get('/crm/patients/{id}/whatsapp-link', [CrmFollowUpController::class, 'whatsappLink'])->middleware([EnsureModuleEntitlement::class.':marketing', 'permission:crm.view'])->name('clinic.crm.whatsapp_link');

            // Executive Analytics & Reports
            Route::get('/analytics', [AnalyticsDashboardController::class, 'index'])->middleware([EnsureModuleEntitlement::class.':analytics', 'permission:finance.reports'])->name('clinic.analytics.index');
            Route::get('/analytics/export', [AnalyticsDashboardController::class, 'export'])->middleware([EnsureModuleEntitlement::class.':analytics', 'permission:finance.reports'])->name('clinic.analytics.export');
        });

        // WhatsApp Webhook (Signed & Idempotent)
        Route::post('/api/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('clinic.whatsapp.webhook');
    });
});
