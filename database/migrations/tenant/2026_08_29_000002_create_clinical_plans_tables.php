<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->create('clinical_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->index();
            $table->uuid('clinical_encounter_id')->index();
            $table->uuid('clinical_diagnosis_id')->nullable()->index();
            $table->string('title');
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->restrictOnDelete();
            $table->foreign('clinical_encounter_id')->references('id')->on('clinical_encounters')->restrictOnDelete();
            $table->foreign('clinical_diagnosis_id')->references('id')->on('clinical_diagnoses')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('clinical_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('clinical_plan_id')->index();
            $table->uuid('procedure_id');
            $table->unsignedSmallInteger('tooth_number')->nullable();
            $table->string('surface', 30)->default('all');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('clinical_note')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->string('status', 30)->default('proposed')->index();
            $table->timestamps();

            $table->foreign('clinical_plan_id')->references('id')->on('clinical_plans')->onDelete('cascade');
            $table->foreign('procedure_id')->references('id')->on('procedures')->restrictOnDelete();
        });

        Schema::connection('tenant')->table('quote_items', function (Blueprint $table) {
            $table->uuid('clinical_plan_item_id')->nullable()->after('quote_id')->index();
            $table->foreign('clinical_plan_item_id')->references('id')->on('clinical_plan_items')->restrictOnDelete();
        });

        Schema::connection('tenant')->table('treatment_plan_items', function (Blueprint $table) {
            $table->uuid('quote_item_id')->nullable()->after('treatment_plan_id')->index();
            $table->uuid('clinical_plan_item_id')->nullable()->after('quote_item_id')->index();
            $table->foreign('quote_item_id')->references('id')->on('quote_items')->restrictOnDelete();
            $table->foreign('clinical_plan_item_id')->references('id')->on('clinical_plan_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('treatment_plan_items', function (Blueprint $table) {
            $table->dropForeign(['quote_item_id']);
            $table->dropForeign(['clinical_plan_item_id']);
            $table->dropColumn(['quote_item_id', 'clinical_plan_item_id']);
        });

        Schema::connection('tenant')->table('quote_items', function (Blueprint $table) {
            $table->dropForeign(['clinical_plan_item_id']);
            $table->dropColumn('clinical_plan_item_id');
        });

        Schema::connection('tenant')->dropIfExists('clinical_plan_items');
        Schema::connection('tenant')->dropIfExists('clinical_plans');
    }
};
