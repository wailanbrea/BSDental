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
        Schema::connection('tenant')->table('patient_charges', function (Blueprint $table) {
            $table->decimal('adjusted_amount', 12, 2)->default(0.00)->after('paid_amount');
        });

        Schema::connection('tenant')->create('credit_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_charge_id');
            $table->uuid('patient_id');
            $table->string('credit_note_number', 50)->index();
            $table->string('type', 40); // subsequent_discount, correction, uncollectible, store_credit, reversal
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->dateTime('adjusted_at')->index();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('patient_charge_id')->references('id')->on('patient_charges')->onDelete('cascade');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('credit_adjustments');

        Schema::connection('tenant')->table('patient_charges', function (Blueprint $table) {
            $table->dropColumn('adjusted_amount');
        });
    }
};
