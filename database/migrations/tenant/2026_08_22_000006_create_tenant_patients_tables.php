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
        Schema::connection('tenant')->create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('record_number')->unique()->index();
            $table->string('first_name')->index();
            $table->string('last_name')->index();
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('phone', 50)->nullable()->index();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('occupation')->nullable();
            $table->string('blood_type', 10)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            $table->boolean('is_minor')->default(false);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_identification')->nullable();
            $table->string('guardian_phone', 50)->nullable();
            $table->string('insurance_company')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('patient_medical_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->json('allergies')->nullable();
            $table->json('systemic_conditions')->nullable();
            $table->json('current_medications')->nullable();
            $table->boolean('is_pregnant')->default(false);
            $table->unsignedInteger('pregnancy_weeks')->nullable();
            $table->boolean('bleeding_disorders')->default(false);
            $table->boolean('has_pacemaker')->default(false);
            $table->text('medical_notes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('patient_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->string('category', 50)->default('document')->index();
            $table->string('filename');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('stored_path');
            $table->text('notes')->nullable();
            $table->uuid('uploaded_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('uploaded_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('patient_files');
        Schema::connection('tenant')->dropIfExists('patient_medical_histories');
        Schema::connection('tenant')->dropIfExists('patients');
    }
};
