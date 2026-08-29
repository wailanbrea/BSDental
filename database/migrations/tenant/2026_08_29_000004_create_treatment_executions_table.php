<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('treatment_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treatment_plan_item_id')->unique();
            $table->uuid('clinical_encounter_id');
            $table->uuid('professional_id');
            $table->uuid('executed_by_user_id');
            $table->dateTime('executed_at')->index();
            $table->timestamps();

            $table->foreign('treatment_plan_item_id')->references('id')->on('treatment_plan_items')->onDelete('cascade');
            $table->foreign('clinical_encounter_id')->references('id')->on('clinical_encounters')->onDelete('restrict');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('restrict');
            $table->foreign('executed_by_user_id')->references('id')->on('users')->onDelete('restrict');
        });

        Schema::connection('tenant')->table('follow_up_tasks', function (Blueprint $table) {
            $table->uuid('treatment_execution_id')->nullable()->after('appointment_id');
            $table->foreign('treatment_execution_id')->references('id')->on('treatment_executions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('follow_up_tasks', function (Blueprint $table) {
            $table->dropForeign(['treatment_execution_id']);
            $table->dropColumn('treatment_execution_id');
        });

        Schema::connection('tenant')->dropIfExists('treatment_executions');
    }
};
