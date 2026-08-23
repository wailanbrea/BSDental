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
        Schema::connection('tenant')->create('appointment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('color', 20)->default('#0d9488');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('professional_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('professional_id');
            $table->uuid('branch_id');
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday, 1=Monday, ..., 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('schedule_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('professional_id')->nullable();
            $table->uuid('room_id')->nullable();
            $table->string('title');
            $table->string('reason', 50)->default('other');
            $table->dateTime('start_time')->index();
            $table->dateTime('end_time')->index();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('professional_id');
            $table->uuid('branch_id');
            $table->uuid('room_id')->nullable();
            $table->uuid('appointment_type_id')->nullable();
            $table->dateTime('start_time')->index();
            $table->dateTime('end_time')->index();
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('status', 30)->default('scheduled')->index();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->uuid('rescheduled_from_id')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('in_progress_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('room_id')->references('id')->on('rooms')->nullOnDelete();
            $table->foreign('appointment_type_id')->references('id')->on('appointment_types')->nullOnDelete();
            $table->foreign('rescheduled_from_id')->references('id')->on('appointments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('appointments');
        Schema::connection('tenant')->dropIfExists('schedule_blocks');
        Schema::connection('tenant')->dropIfExists('professional_schedules');
        Schema::connection('tenant')->dropIfExists('appointment_types');
    }
};
