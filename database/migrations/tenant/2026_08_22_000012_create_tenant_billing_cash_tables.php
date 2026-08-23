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
        Schema::connection('tenant')->create('cash_registers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_register_id');
            $table->uuid('opened_by_user_id');
            $table->uuid('closed_by_user_id')->nullable();
            $table->string('status', 30)->default('open')->index(); // open, closing_review, closed
            $table->decimal('opening_balance', 12, 2)->default(0.00);
            $table->decimal('expected_cash', 12, 2)->default(0.00);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->default(0.00);
            $table->dateTime('opened_at')->index();
            $table->dateTime('closed_at')->nullable()->index();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->onDelete('cascade');
            $table->foreign('opened_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('closed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cash_session_id');
            $table->string('type', 40)->index(); // manual_income, manual_expense, patient_payment, patient_refund
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30)->default('cash'); // cash, card, transfer, check, insurance
            $table->string('concept');
            $table->uuid('created_by_user_id')->nullable();
            $table->dateTime('created_at')->index();

            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('patient_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('treatment_plan_item_id')->nullable();
            $table->uuid('professional_id')->nullable();
            $table->string('charge_number', 50)->index();
            $table->string('concept');
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0.00);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0.00);
            $table->decimal('balance_due', 12, 2);
            $table->string('status', 30)->default('pending')->index(); // pending, partially_paid, paid, cancelled
            $table->date('due_date')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('treatment_plan_item_id')->references('id')->on('treatment_plan_items')->nullOnDelete();
            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('cash_session_id')->nullable();
            $table->string('payment_number', 50)->index();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('allocated_amount', 12, 2)->default(0.00);
            $table->decimal('unallocated_amount', 12, 2)->default(0.00);
            $table->decimal('refunded_amount', 12, 2)->default(0.00);
            $table->string('status', 30)->default('confirmed')->index(); // confirmed, partially_allocated, fully_allocated, refunded, cancelled
            $table->dateTime('paid_at')->index();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('payment_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->string('method', 30); // cash, credit_card, debit_card, transfer, zelle, insurance
            $table->decimal('amount', 12, 2);
            $table->string('reference_code', 100)->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('patient_charge_id');
            $table->decimal('amount', 12, 2);
            $table->dateTime('allocated_at')->index();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('patient_charge_id')->references('id')->on('patient_charges')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_id');
            $table->uuid('patient_id');
            $table->uuid('cash_session_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->dateTime('refunded_at')->index();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('professional_compensations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('professional_id');
            $table->uuid('patient_charge_id')->nullable();
            $table->string('rule_type', 40); // percentage_production, percentage_collected, fixed_procedure
            $table->decimal('rate', 5, 2);
            $table->decimal('base_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->string('status', 30)->default('accrued')->index(); // accrued, settled, paid
            $table->dateTime('accrued_at')->index();
            $table->dateTime('settled_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->foreign('patient_charge_id')->references('id')->on('patient_charges')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('professional_compensations');
        Schema::connection('tenant')->dropIfExists('refunds');
        Schema::connection('tenant')->dropIfExists('payment_allocations');
        Schema::connection('tenant')->dropIfExists('payment_splits');
        Schema::connection('tenant')->dropIfExists('payments');
        Schema::connection('tenant')->dropIfExists('patient_charges');
        Schema::connection('tenant')->dropIfExists('cash_movements');
        Schema::connection('tenant')->dropIfExists('cash_sessions');
        Schema::connection('tenant')->dropIfExists('cash_registers');
    }
};
