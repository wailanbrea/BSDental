<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('appointments', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal')->index()->after('status');
        });

        Schema::connection('tenant')->table('patients', function (Blueprint $table) {
            $table->string('patient_type', 30)->default('returning')->index()->after('status');
        });

        Schema::connection('tenant')->table('follow_up_tasks', function (Blueprint $table) {
            $table->string('completion_channel', 20)->nullable()->after('completed_at');
            $table->string('completion_result', 30)->nullable()->after('completion_channel');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('follow_up_tasks', function (Blueprint $table) {
            $table->dropColumn(['completion_channel', 'completion_result']);
        });

        Schema::connection('tenant')->table('patients', function (Blueprint $table) {
            $table->dropColumn('patient_type');
        });

        Schema::connection('tenant')->table('appointments', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
