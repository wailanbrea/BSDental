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
        Schema::connection('tenant')->create('consent_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->index();
            $table->string('title');
            $table->unsignedInteger('version')->default(1);
            $table->longText('content');
            $table->boolean('required_witness')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('patient_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('consent_template_id');
            $table->unsignedInteger('template_version');
            $table->string('title');
            $table->longText('rendered_content');
            $table->string('signed_by_name');
            $table->string('signed_by_identification', 50)->nullable();
            $table->string('relationship', 50)->default('patient');
            $table->string('signature_type', 30)->default('drawn');
            $table->longText('signature_data');
            $table->dateTime('signed_at')->index();
            $table->string('signed_ip', 45)->nullable();
            $table->string('integrity_hash', 64);
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('consent_template_id')->references('id')->on('consent_templates')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('odontograms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->unique();
            $table->string('type', 30)->default('adult'); // adult, pediatric, mixed
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('odontogram_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('odontogram_id');
            $table->uuid('encounter_id')->nullable();
            $table->unsignedSmallInteger('tooth_number'); // FDI 11-48, 51-85
            $table->string('surface', 30)->default('all'); // all, vestibular, lingual_palatal, mesial, distal, occlusal_incisal
            $table->string('condition', 40); // caries, restored_composite, restored_amalgam, crown, endodontic, missing, implant, prosthesis, sealant, fracture, healthy
            $table->string('lifecycle_state', 30)->default('initial_diagnosis')->index(); // initial_diagnosis, planned, approved, completed
            $table->text('notes')->nullable();
            $table->uuid('recorded_by_user_id')->nullable();
            $table->dateTime('recorded_at')->index();
            $table->timestamps();

            $table->foreign('odontogram_id')->references('id')->on('odontograms')->onDelete('cascade');
            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->nullOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('odontogram_entries');
        Schema::connection('tenant')->dropIfExists('odontograms');
        Schema::connection('tenant')->dropIfExists('patient_consents');
        Schema::connection('tenant')->dropIfExists('consent_templates');
    }
};
