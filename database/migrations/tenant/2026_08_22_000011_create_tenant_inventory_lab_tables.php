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
        Schema::connection('tenant')->create('inventory_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('inventory_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('sku', 50)->nullable()->index();
            $table->string('name');
            $table->string('unit', 30)->default('unit'); // unit, box, syringe, bottle, pair, gram, ml
            $table->decimal('min_stock', 12, 2)->default(5.00);
            $table->decimal('cost_price', 12, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('inventory_categories')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('warehouses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('name');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('inventory_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_item_id');
            $table->uuid('warehouse_id');
            $table->string('batch_number', 50)->index();
            $table->decimal('initial_quantity', 12, 2);
            $table->decimal('current_quantity', 12, 2);
            $table->decimal('cost_per_unit', 12, 2);
            $table->date('expires_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_item_id');
            $table->uuid('warehouse_id');
            $table->uuid('batch_id')->nullable();
            $table->string('type', 40)->index(); // purchase_in, procedure_consumption, transfer_in, transfer_out, adjustment_in, adjustment_out, waste_loss
            $table->decimal('quantity', 12, 2);
            $table->decimal('previous_stock', 12, 2);
            $table->decimal('new_stock', 12, 2);
            $table->decimal('unit_cost', 12, 2)->default(0.00);
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->string('reference_type', 60)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->dateTime('created_at')->index();

            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('inventory_batches')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('procedure_material_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('procedure_id');
            $table->uuid('inventory_item_id');
            $table->decimal('quantity_required', 12, 2)->default(1.00);
            $table->timestamps();

            $table->foreign('procedure_id')->references('id')->on('procedures')->onDelete('cascade');
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('dental_laboratories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('lab_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('laboratory_id');
            $table->uuid('treatment_plan_item_id')->nullable();
            $table->string('order_number', 50)->index();
            $table->unsignedSmallInteger('tooth_number')->nullable();
            $table->text('work_description');
            $table->string('shade_guide', 50)->nullable();
            $table->string('status', 30)->default('draft')->index(); // draft, ordered, sent, in_progress, ready, received, delivered, cancelled
            $table->date('sent_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('estimated_cost', 12, 2)->default(0.00);
            $table->decimal('final_cost', 12, 2)->default(0.00);
            $table->string('payable_status', 30)->default('unpaid')->index(); // unpaid, partially_paid, paid
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('laboratory_id')->references('id')->on('dental_laboratories')->onDelete('cascade');
            $table->foreign('treatment_plan_item_id')->references('id')->on('treatment_plan_items')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('lab_orders');
        Schema::connection('tenant')->dropIfExists('dental_laboratories');
        Schema::connection('tenant')->dropIfExists('procedure_material_rules');
        Schema::connection('tenant')->dropIfExists('stock_movements');
        Schema::connection('tenant')->dropIfExists('inventory_batches');
        Schema::connection('tenant')->dropIfExists('warehouses');
        Schema::connection('tenant')->dropIfExists('inventory_items');
        Schema::connection('tenant')->dropIfExists('inventory_categories');
    }
};
