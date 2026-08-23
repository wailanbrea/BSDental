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
        Schema::connection('tenant')->create('clinic_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('clinic_name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('currency')->default('USD');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('clinic_profiles');
    }
};
