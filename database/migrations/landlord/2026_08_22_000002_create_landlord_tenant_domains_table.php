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
        Schema::connection('landlord')->create('tenant_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->index(['domain', 'is_verified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('tenant_domains');
    }
};
