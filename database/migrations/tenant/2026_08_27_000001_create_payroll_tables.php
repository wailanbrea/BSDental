<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('treatment_plan_items', function (Blueprint $table) {
            $table->uuid('professional_id')->nullable()->index();
        });

        Schema::connection('tenant')->table('professional_compensations', function (Blueprint $table) {
            $table->uuid('treatment_plan_item_id')->nullable()->unique();
        });

        Schema::connection('tenant')->create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_number', 30)->unique();
            $table->uuid('professional_id')->nullable()->unique();
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('compensation_type', 30)->index(); // fixed_salary, commission
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('professional_id')->references('id')->on('professionals')->nullOnDelete();
        });

        Schema::connection('tenant')->create('payroll_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('run_number', 40)->unique();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('fixed_salary_total', 12, 2)->default(0);
            $table->decimal('commission_total', 12, 2)->default(0);
            $table->decimal('net_total', 12, 2)->default(0);
            $table->dateTime('paid_at')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('paid_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('paid_by_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('payroll_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_run_id');
            $table->uuid('employee_id');
            $table->decimal('fixed_salary_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->json('employee_snapshot');
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->restrictOnDelete();
        });

        Schema::connection('tenant')->create('payroll_item_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payroll_item_id');
            $table->uuid('professional_compensation_id')->nullable()->unique();
            $table->string('type', 30);
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->onDelete('cascade');
            $table->foreign('professional_compensation_id')->references('id')->on('professional_compensations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payroll_item_lines');
        Schema::connection('tenant')->dropIfExists('payroll_items');
        Schema::connection('tenant')->dropIfExists('payroll_runs');
        Schema::connection('tenant')->dropIfExists('employees');

        Schema::connection('tenant')->table('professional_compensations', function (Blueprint $table) {
            $table->dropColumn('treatment_plan_item_id');
        });

        Schema::connection('tenant')->table('treatment_plan_items', function (Blueprint $table) {
            $table->dropColumn('professional_id');
        });
    }
};
