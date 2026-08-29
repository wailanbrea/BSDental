<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'landlord';

    public function up(): void
    {
        DB::connection('landlord')
            ->table('plans')
            ->where('name', 'Plan Enterprise Multi-Sede')
            ->update([
                'modules' => json_encode([
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
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::connection('landlord')
            ->table('plans')
            ->where('name', 'Plan Enterprise Multi-Sede')
            ->update([
                'modules' => json_encode([
                    'clinical',
                    'billing',
                    'cash',
                    'inventory',
                    'lab',
                    'crm',
                    'analytics',
                    'multi_branch',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
    }
};
