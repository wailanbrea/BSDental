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
        Schema::connection('landlord')->create('plans', function (Blueprint $table) {
            $table->string('id')->primary(); // starter, pro, enterprise
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('modules');
            $table->integer('max_users')->default(5);
            $table->integer('max_branches')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('plans');
    }
};
