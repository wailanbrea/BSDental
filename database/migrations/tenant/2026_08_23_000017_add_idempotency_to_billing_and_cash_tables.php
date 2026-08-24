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
        Schema::connection('tenant')->table('payments', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->unique()->after('status');
        });

        Schema::connection('tenant')->table('refunds', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->unique()->after('reason');
        });

        Schema::connection('tenant')->table('cash_movements', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable()->unique()->after('concept');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('cash_movements', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });

        Schema::connection('tenant')->table('refunds', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });

        Schema::connection('tenant')->table('payments', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
