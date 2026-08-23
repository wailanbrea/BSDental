<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The database connection that should be used by the migration.
     */
    protected $connection = 'landlord';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('landlord')->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('database_name');
            $table->string('database_host')->nullable();
            $table->string('database_username')->nullable();
            $table->text('database_password')->nullable();
            $table->string('status')->default('provisioning'); // provisioning, active, suspended, archived
            $table->string('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenants');
    }
};
