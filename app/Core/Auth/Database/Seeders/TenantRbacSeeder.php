<?php

namespace App\Core\Auth\Database\Seeders;

use App\Core\Auth\Models\Permission;
use App\Core\Auth\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class TenantRbacSeeder extends Seeder
{
    /**
     * Run the tenant RBAC seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // 1. Catálogo de Permisos
        $permissions = [
            // Pacientes
            'patients.view',
            'patients.create',
            'patients.update',
            'patients.delete',

            // Citas y Agenda
            'appointments.view',
            'appointments.create',
            'appointments.update',
            'appointments.cancel',

            // Ficha Clínica y Evolución
            'clinical.view',
            'clinical.write',
            'clinical.finalize',

            // Odontograma y Tratamientos
            'odontogram.view',
            'odontogram.write',

            // Presupuestos y Cotizaciones
            'quotes.create',
            'quotes.approve',
            'quotes.discount',

            // Pagos y Facturación
            'payments.create',
            'payments.refund',

            // Caja
            'cash.open',
            'cash.close',
            'cash.reopen',

            // Finanzas y Reportes
            'finance.view',
            'finance.reports',

            // Inventario
            'inventory.view',
            'inventory.adjust',
            'inventory.purchase',

            // Laboratorio
            'lab.view',
            'lab.order',
            'lab.receive',

            // Configuración y Usuarios de la Clínica
            'settings.view',
            'settings.update',
            'users.view',
            'users.manage',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // 2. Roles y Asignación de Permisos
        $roles = [
            'Owner' => $permissions, // Acceso total

            'ClinicDirector' => [
                'patients.view', 'patients.create', 'patients.update',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
                'clinical.view', 'clinical.write', 'clinical.finalize',
                'odontogram.view', 'odontogram.write',
                'quotes.create', 'quotes.approve', 'quotes.discount',
                'payments.create',
                'finance.view', 'finance.reports',
                'inventory.view', 'inventory.purchase',
                'lab.view', 'lab.order',
                'users.view',
            ],

            'GeneralDentist' => [
                'patients.view', 'patients.create', 'patients.update',
                'appointments.view', 'appointments.create', 'appointments.update',
                'clinical.view', 'clinical.write',
                'odontogram.view', 'odontogram.write',
                'quotes.create',
            ],

            'SpecialistDentist' => [
                'patients.view',
                'appointments.view', 'appointments.create',
                'clinical.view', 'clinical.write',
                'odontogram.view', 'odontogram.write',
            ],

            'Hygienist' => [
                'patients.view',
                'appointments.view', 'appointments.create',
                'clinical.view', 'clinical.write',
            ],

            'Receptionist' => [
                'patients.view', 'patients.create', 'patients.update',
                'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
                'quotes.create',
                'payments.create',
                'cash.open', 'cash.close',
            ],

            'Cashier' => [
                'patients.view',
                'payments.create', 'payments.refund',
                'cash.open', 'cash.close', 'cash.reopen',
                'finance.view',
            ],

            'LabTechnician' => [
                'lab.view', 'lab.order', 'lab.receive',
            ],

            'InventoryManager' => [
                'inventory.view', 'inventory.adjust', 'inventory.purchase',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }
    }
}
