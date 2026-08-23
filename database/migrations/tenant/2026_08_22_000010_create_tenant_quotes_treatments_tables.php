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
        Schema::connection('tenant')->create('procedure_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('color', 20)->default('#0d9488');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('procedures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('code', 50)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('estimated_minutes')->default(30);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->boolean('requires_lab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('procedure_categories')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('professional_id')->nullable();
            $table->string('quote_number', 50)->index();
            $table->unsignedInteger('version')->default(1);
            $table->string('alternative_name')->default('Plan Principal');
            $table->string('status', 30)->default('draft')->index(); // draft, presented, approved, partially_approved, rejected, expired, converted
            $table->decimal('subtotal', 12, 2)->default(0.00);
            $table->decimal('discount_total', 12, 2)->default(0.00);
            $table->decimal('tax_total', 12, 2)->default(0.00);
            $table->decimal('grand_total', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('quote_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_id');
            $table->uuid('procedure_id');
            $table->unsignedSmallInteger('tooth_number')->nullable();
            $table->string('surface', 30)->default('all');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax', 12, 2)->default(0.00);
            $table->decimal('total', 12, 2);
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->foreign('quote_id')->references('id')->on('quotes')->onDelete('cascade');
            $table->foreign('procedure_id')->references('id')->on('procedures')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('treatment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('quote_id')->nullable();
            $table->string('title');
            $table->string('status', 30)->default('active')->index(); // active, completed, cancelled
            $table->decimal('total_estimated', 12, 2)->default(0.00);
            $table->decimal('total_performed', 12, 2)->default(0.00);
            $table->decimal('progress_percentage', 5, 2)->default(0.00);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('treatment_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('treatment_plan_id');
            $table->uuid('procedure_id');
            $table->unsignedSmallInteger('tooth_number')->nullable();
            $table->string('surface', 30)->default('all');
            $table->unsignedInteger('phase')->default(1);
            $table->decimal('price', 12, 2);
            $table->string('status', 30)->default('pending')->index(); // pending, scheduled, in_progress, completed
            $table->uuid('appointment_id')->nullable();
            $table->uuid('encounter_id')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->uuid('completed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('cascade');
            $table->foreign('procedure_id')->references('id')->on('procedures')->onDelete('cascade');
            $table->foreign('appointment_id')->references('id')->on('appointments')->nullOnDelete();
            $table->foreign('encounter_id')->references('id')->on('clinical_encounters')->nullOnDelete();
            $table->foreign('completed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('treatment_plan_items');
        Schema::connection('tenant')->dropIfExists('treatment_plans');
        Schema::connection('tenant')->dropIfExists('quote_items');
        Schema::connection('tenant')->dropIfExists('quotes');
        Schema::connection('tenant')->dropIfExists('procedures');
        Schema::connection('tenant')->dropIfExists('procedure_categories');
    }
};
