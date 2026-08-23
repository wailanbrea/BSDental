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
        Schema::connection('tenant')->create('follow_up_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('appointment_id')->nullable();
            $table->string('type', 40)->index(); // post_op, no_show, quote_pending, treatment_incomplete, periodic_recall
            $table->string('title');
            $table->date('due_date')->index();
            $table->string('priority', 20)->default('medium'); // low, medium, high
            $table->string('status', 30)->default('pending')->index(); // pending, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->uuid('assigned_to_user_id')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20); // internal, email, whatsapp, sms
            $table->string('trigger_event', 50)->index();
            $table->string('name');
            $table->text('body_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('appointment_id')->nullable();
            $table->string('channel', 20)->default('whatsapp');
            $table->string('recipient');
            $table->string('status', 30)->default('scheduled')->index(); // scheduled, queued, sent, delivered, read, failed, responded, cancelled
            $table->text('content');
            $table->string('provider_message_id', 150)->nullable()->index();
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
        });

        Schema::connection('tenant')->create('crm_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->default('#38bdf8');
            $table->integer('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('patient_crm_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id')->unique();
            $table->uuid('stage_id');
            $table->string('source', 50)->nullable();
            $table->decimal('estimated_lifetime_value', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('stage_id')->references('id')->on('crm_stages')->onDelete('restrict');
        });

        Schema::connection('tenant')->create('patient_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('criteria_json');
            $table->integer('patient_count')->default(0);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('marketing_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('channel', 20)->default('whatsapp');
            $table->uuid('segment_id')->nullable();
            $table->text('message_body');
            $table->string('status', 30)->default('draft')->index(); // draft, scheduled, sending, completed
            $table->dateTime('scheduled_at')->nullable();
            $table->integer('sent_count')->default(0);
            $table->timestamps();

            $table->foreign('segment_id')->references('id')->on('patient_segments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('marketing_campaigns');
        Schema::connection('tenant')->dropIfExists('patient_segments');
        Schema::connection('tenant')->dropIfExists('patient_crm_profiles');
        Schema::connection('tenant')->dropIfExists('crm_stages');
        Schema::connection('tenant')->dropIfExists('notification_logs');
        Schema::connection('tenant')->dropIfExists('notification_templates');
        Schema::connection('tenant')->dropIfExists('follow_up_tasks');
    }
};
