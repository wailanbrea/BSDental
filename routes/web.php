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
use App\Core\Controllers\ClinicDashboardController;
use App\Core\Controllers\ClinicSettingsController;
use App\Core\Controllers\ConsentController;
use App\Core\Controllers\CrmFollowUpController;
use App\Core\Controllers\DentalLabController;
use App\Core\Controllers\InventoryController;
use App\Core\Controllers\NotificationCenterController;
use App\Core\Controllers\OdontogramController;
use App\Core\Controllers\PatientController;
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

        Route::middleware([RequireTenantTwoFactor::class])->group(function () {
            Route::get('/dashboard', [ClinicDashboardController::class, 'index'])->name('clinic.dashboard');

            Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('clinic.notifications.index');
            Route::patch('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('clinic.notifications.read_all');
            Route::patch('/notifications/{id}/read', [NotificationCenterController::class, 'markRead'])->name('clinic.notifications.read');

            // Clinic Core Management
            Route::get('/settings', [ClinicSettingsController::class, 'edit'])->name('clinic.settings');
            Route::put('/settings', [ClinicSettingsController::class, 'update']);

            Route::get('/branches', [BranchController::class, 'index'])->name('clinic.branches');
            Route::post('/branches', [BranchController::class, 'store']);
            Route::put('/branches/{branch}', [BranchController::class, 'update']);
            Route::delete('/branches/{branch}', [BranchController::class, 'destroy']);

            Route::post('/branches/{branch}/rooms', [RoomController::class, 'store']);
            Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);

            Route::get('/professionals', [ProfessionalController::class, 'index'])->name('clinic.professionals');
            Route::post('/professionals', [ProfessionalController::class, 'store']);
            Route::put('/professionals/{professional}', [ProfessionalController::class, 'update']);
            Route::delete('/professionals/{professional}', [ProfessionalController::class, 'destroy']);

            // Patients & Clinical Records
            Route::get('/patients', [PatientController::class, 'index'])->name('clinic.patients.index');
            Route::get('/patients/create', [PatientController::class, 'create'])->name('clinic.patients.create');
            Route::get('/patients/check-duplicates', [PatientController::class, 'checkDuplicates'])->name('clinic.patients.check_duplicates');
            Route::post('/patients', [PatientController::class, 'store'])->name('clinic.patients.store');
            Route::get('/patients/{id}/edit', [PatientController::class, 'edit'])->name('clinic.patients.edit');
            Route::get('/patients/{id}', [PatientController::class, 'show'])->name('clinic.patients.show');
            Route::put('/patients/{id}', [PatientController::class, 'update'])->name('clinic.patients.update');
            Route::post('/patients/{id}/files', [PatientController::class, 'uploadFile'])->name('clinic.patients.upload_file');
            Route::delete('/patients/{id}', [PatientController::class, 'destroy'])->name('clinic.patients.destroy');

            // Agenda & Appointments
            Route::get('/appointments', [AppointmentController::class, 'index'])->name('clinic.appointments.index');
            Route::post('/appointments', [AppointmentController::class, 'store'])->name('clinic.appointments.store');
            Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus'])->name('clinic.appointments.update_status');
            Route::post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'])->name('clinic.appointments.reschedule');
            Route::post('/appointments/blocks', [AppointmentController::class, 'createBlock'])->name('clinic.appointments.create_block');

            // Clinical Encounters & Medical Records (Inmutable & Amendments)
            Route::get('/encounters', [ClinicalEncounterController::class, 'index'])->name('clinic.encounters.all');
            Route::get('/patients/{patientId}/encounters', [ClinicalEncounterController::class, 'index'])->name('clinic.encounters.index');
            Route::get('/patients/{patientId}/encounters/create', [ClinicalEncounterController::class, 'create'])->name('clinic.encounters.create');
            Route::post('/encounters', [ClinicalEncounterController::class, 'store'])->name('clinic.encounters.store');
            Route::get('/encounters/{id}', [ClinicalEncounterController::class, 'show'])->name('clinic.encounters.show');
            Route::put('/encounters/{id}', [ClinicalEncounterController::class, 'update'])->name('clinic.encounters.update');
            Route::post('/encounters/{id}/finalize', [ClinicalEncounterController::class, 'finalize'])->name('clinic.encounters.finalize');
            Route::post('/encounters/{id}/amend', [ClinicalEncounterController::class, 'amend'])->name('clinic.encounters.amend');

            // Odontogram FDI
            Route::get('/patients/{patientId}/odontogram', [OdontogramController::class, 'show'])->name('clinic.odontogram.show');
            Route::post('/patients/{patientId}/odontogram/entries', [OdontogramController::class, 'storeEntry'])->name('clinic.odontogram.store_entry');

            // Informed Consents
            Route::get('/patients/{patientId}/consents', [ConsentController::class, 'index'])->name('clinic.consents.index');
            Route::post('/patients/{patientId}/consents', [ConsentController::class, 'store'])->name('clinic.consents.store');

            // Procedures Catalog & Pricing
            Route::get('/procedures', [ProcedureCatalogController::class, 'index'])->name('clinic.procedures.index');
            Route::post('/procedures', [ProcedureCatalogController::class, 'store'])->name('clinic.procedures.store');

            // Quotes / Presupuestos
            Route::get('/quotes', [QuoteController::class, 'allIndex'])->name('clinic.quotes.all');
            Route::get('/quotes/quick-create', [QuoteController::class, 'quickCreate'])->name('clinic.quotes.quick_create');
            Route::post('/quotes/quick', [QuoteController::class, 'storeQuick'])->name('clinic.quotes.store_quick');
            Route::get('/patients/{patientId}/quotes', [QuoteController::class, 'index'])->name('clinic.quotes.index');
            Route::get('/patients/{patientId}/quotes/create', [QuoteController::class, 'create'])->name('clinic.quotes.create');
            Route::post('/patients/{patientId}/quotes', [QuoteController::class, 'store'])->name('clinic.quotes.store');
            Route::get('/quotes/{id}', [QuoteController::class, 'show'])->name('clinic.quotes.show');
            Route::post('/quotes/{id}/convert-to-patient', [QuoteController::class, 'convertToPatient'])->name('clinic.quotes.convert_to_patient');
            Route::post('/quotes/{id}/approve', [QuoteController::class, 'approve'])->name('clinic.quotes.approve');
            Route::post('/quotes/{id}/reject', [QuoteController::class, 'reject'])->name('clinic.quotes.reject');

            // Treatment Plans
            Route::get('/patients/{patientId}/treatment-plans', [TreatmentPlanController::class, 'index'])->name('clinic.treatment_plans.index');
            Route::get('/treatment-plans/{id}', [TreatmentPlanController::class, 'show'])->name('clinic.treatment_plans.show');
            Route::post('/treatment-items/{itemId}/complete', [TreatmentPlanController::class, 'completeItem'])->name('clinic.treatment_items.complete');

            // Inventory & Stock Ledger
            Route::get('/inventory', [InventoryController::class, 'index'])->name('clinic.inventory.index');
            Route::post('/inventory/items', [InventoryController::class, 'storeItem'])->name('clinic.inventory.store_item');
            Route::post('/inventory/purchases', [InventoryController::class, 'recordPurchase'])->name('clinic.inventory.record_purchase');

            // Dental Laboratory & Prosthesis
            Route::get('/lab', [DentalLabController::class, 'index'])->name('clinic.lab.index');
            Route::post('/lab/orders', [DentalLabController::class, 'storeOrder'])->name('clinic.lab.store_order');
            Route::post('/lab/orders/{id}/status', [DentalLabController::class, 'updateStatus'])->name('clinic.lab.update_status');

            // Patient Billing & Invoicing
            Route::get('/patients/{patientId}/billing', [BillingController::class, 'index'])->name('clinic.billing.index');
            Route::post('/patients/{patientId}/billing/charges', [BillingController::class, 'storeCharge'])->name('clinic.billing.store_charge');
            Route::post('/patients/{patientId}/billing/payments', [BillingController::class, 'storePayment'])->name('clinic.billing.store_payment');
            Route::post('/payments/{paymentId}/allocate', [BillingController::class, 'allocate'])->name('clinic.billing.allocate');
            Route::post('/payments/{paymentId}/refund', [BillingController::class, 'refund'])->name('clinic.billing.refund');

            // Cash Registers & Sessions
            Route::get('/cash-registers', [CashRegisterController::class, 'index'])->name('clinic.cash.index');
            Route::post('/cash-registers/{id}/open', [CashRegisterController::class, 'open'])->name('clinic.cash.open');
            Route::post('/cash-sessions/{id}/close', [CashRegisterController::class, 'close'])->name('clinic.cash.close');

            // CRM, Recalls & Follow-up
            Route::get('/crm', [CrmFollowUpController::class, 'index'])->name('clinic.crm.index');
            Route::post('/crm/tasks', [CrmFollowUpController::class, 'storeTask'])->name('clinic.crm.store_task');
            Route::post('/crm/tasks/{id}/complete', [CrmFollowUpController::class, 'completeTask'])->name('clinic.crm.complete_task');

            // Executive Analytics & Reports
            Route::get('/analytics', [AnalyticsDashboardController::class, 'index'])->name('clinic.analytics.index');
            Route::get('/analytics/export', [AnalyticsDashboardController::class, 'export'])->name('clinic.analytics.export');
        });

        // WhatsApp Webhook (Signed & Idempotent)
        Route::post('/api/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('clinic.whatsapp.webhook');
    });
});
