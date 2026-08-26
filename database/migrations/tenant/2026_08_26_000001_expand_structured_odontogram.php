<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('odontogram_entries', function (Blueprint $table) {
            $table->string('entry_type', 30)->default('finding')->index();
            $table->json('surfaces')->nullable();
            $table->string('code_system', 80)->nullable();
            $table->string('clinical_code', 80)->nullable();
            $table->string('clinical_display')->nullable();
            $table->string('clinical_status', 30)->default('active')->index();
            $table->string('verification_status', 30)->default('confirmed');
            $table->uuid('procedure_id')->nullable()->index();
            $table->uuid('supersedes_entry_id')->nullable()->index();
            $table->text('amendment_reason')->nullable();
            $table->json('device_details')->nullable();
        });

        Schema::connection('tenant')->table('odontograms', function (Blueprint $table) {
            $table->string('caries_risk_level', 20)->nullable();
            $table->json('caries_risk_factors')->nullable();
            $table->dateTime('caries_risk_assessed_at')->nullable();
        });

        Schema::connection('tenant')->table('patient_files', function (Blueprint $table) {
            $table->unsignedSmallInteger('tooth_number')->nullable()->index();
            $table->uuid('odontogram_entry_id')->nullable()->index();
            $table->uuid('encounter_id')->nullable()->index();
            $table->dateTime('taken_at')->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::connection('tenant')->create('periodontal_exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('odontogram_id');
            $table->uuid('encounter_id')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by_user_id')->nullable();
            $table->dateTime('recorded_at')->index();
            $table->timestamps();

            $table->foreign('odontogram_id')->references('id')->on('odontograms')->cascadeOnDelete();
            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->nullOnDelete();
            $table->foreign('recorded_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('periodontal_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('periodontal_exam_id');
            $table->unsignedSmallInteger('tooth_number');
            $table->string('site', 4);
            $table->unsignedTinyInteger('probing_depth')->nullable();
            $table->tinyInteger('recession')->nullable();
            $table->boolean('bleeding')->default(false);
            $table->boolean('plaque')->default(false);
            $table->boolean('suppuration')->default(false);
            $table->unsignedTinyInteger('mobility')->nullable();
            $table->unsignedTinyInteger('furcation')->nullable();
            $table->boolean('is_implant')->default(false);
            $table->timestamps();

            $table->unique(['periodontal_exam_id', 'tooth_number', 'site'], 'periodontal_exam_tooth_site_unique');
            $table->foreign('periodontal_exam_id')->references('id')->on('periodontal_exams')->cascadeOnDelete();
        });

        DB::connection('tenant')->table('odontogram_entries')->orderBy('recorded_at')->each(function (object $entry): void {
            $type = match ($entry->condition) {
                'caries', 'fracture' => 'diagnosis',
                'missing' => 'anatomical_state',
                'implant' => 'device',
                'restored_composite', 'restored_amalgam', 'crown', 'endodontic', 'prosthesis', 'sealant' => 'procedure',
                default => 'finding',
            };

            DB::connection('tenant')->table('odontogram_entries')->where('id', $entry->id)->update([
                'entry_type' => $type,
                'surfaces' => json_encode([$entry->surface], JSON_THROW_ON_ERROR),
                'clinical_display' => $entry->condition,
                'clinical_status' => $entry->lifecycle_state === 'completed' ? 'resolved' : 'active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('periodontal_measurements');
        Schema::connection('tenant')->dropIfExists('periodontal_exams');

        Schema::connection('tenant')->table('patient_files', function (Blueprint $table) {
            $table->dropColumn(['tooth_number', 'odontogram_entry_id', 'encounter_id', 'taken_at', 'metadata']);
        });

        Schema::connection('tenant')->table('odontograms', function (Blueprint $table) {
            $table->dropColumn(['caries_risk_level', 'caries_risk_factors', 'caries_risk_assessed_at']);
        });

        Schema::connection('tenant')->table('odontogram_entries', function (Blueprint $table) {
            $table->dropColumn([
                'entry_type', 'surfaces', 'code_system', 'clinical_code', 'clinical_display',
                'clinical_status', 'verification_status', 'procedure_id', 'supersedes_entry_id',
                'amendment_reason', 'device_details',
            ]);
        });
    }
};
