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
        Schema::connection('tenant')->create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_main')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('tenant')->create('rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        Schema::connection('tenant')->create('specialties', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('professionals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('license_number')->nullable(); // Colegiatura profesional
            $table->string('color', 20)->default('#0d9488'); // Color en Agenda
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::connection('tenant')->create('branch_professional', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('professional_id');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->primary(['branch_id', 'professional_id']);
        });

        Schema::connection('tenant')->create('professional_specialty', function (Blueprint $table) {
            $table->uuid('professional_id');
            $table->uuid('specialty_id');

            $table->foreign('professional_id')->references('id')->on('professionals')->onDelete('cascade');
            $table->foreign('specialty_id')->references('id')->on('specialties')->onDelete('cascade');
            $table->primary(['professional_id', 'specialty_id']);
        });

        Schema::connection('tenant')->create('branch_user', function (Blueprint $table) {
            $table->uuid('branch_id');
            $table->uuid('user_id');

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->primary(['branch_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('branch_user');
        Schema::connection('tenant')->dropIfExists('professional_specialty');
        Schema::connection('tenant')->dropIfExists('branch_professional');
        Schema::connection('tenant')->dropIfExists('professionals');
        Schema::connection('tenant')->dropIfExists('specialties');
        Schema::connection('tenant')->dropIfExists('rooms');
        Schema::connection('tenant')->dropIfExists('branches');
    }
};
