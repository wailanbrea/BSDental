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
        Schema::connection('tenant')->create('clinical_encounters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('professional_id');
            $table->uuid('appointment_id')->nullable();
            $table->dateTime('encounter_date')->index();
            $table->text('chief_complaint')->nullable();
            $table->text('physical_examination')->nullable();
            $table->json('vital_signs')->nullable();
            $table->string('status', 30)->default('draft')->index(); // draft, finalized, amended
            $table->dateTime('finalized_at')->nullable();
            $table->uuid('finalized_by_user_id')->nullable();
            $table->string('integrity_hash', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('finalized_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('clinical_diagnoses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id');
            $table->string('code', 50)->nullable();
            $table->string('description');
            $table->string('type', 30)->default('definitive'); // presumptive, definitive
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('clinical_evolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id');
            $table->text('subjective')->nullable();
            $table->text('objective')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->text('treatment_performed')->nullable();
            $table->text('recommendations')->nullable();
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('clinical_prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id');
            $table->string('medication_name');
            $table->string('dosage', 100);
            $table->string('frequency', 100);
            $table->string('duration', 100);
            $table->text('instructions')->nullable();
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('clinical_amendments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('encounter_id');
            $table->text('reason');
            $table->json('amended_content');
            $table->uuid('amended_by_user_id');
            $table->dateTime('amended_at');
            $table->string('integrity_hash', 64);
            $table->timestamps();

            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->onDelete('cascade');
            $table->foreign('amended_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('clinical_amendments');
        Schema::connection('tenant')->dropIfExists('clinical_prescriptions');
        Schema::connection('tenant')->dropIfExists('clinical_evolutions');
        Schema::connection('tenant')->dropIfExists('clinical_diagnoses');
        Schema::connection('tenant')->dropIfExists('clinical_encounters');
    }
};
