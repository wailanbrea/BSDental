<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('tenant')->table('patients', function (Blueprint $table) {
            $table->boolean('whatsapp_opt_in')->default(true)->after('status');
        });

        Schema::connection('tenant')->table('patient_crm_profiles', function (Blueprint $table) {
            $table->string('loss_reason', 60)->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('patient_crm_profiles', function (Blueprint $table) {
            $table->dropColumn('loss_reason');
        });

        Schema::connection('tenant')->table('patients', function (Blueprint $table) {
            $table->dropColumn('whatsapp_opt_in');
        });
    }
};
