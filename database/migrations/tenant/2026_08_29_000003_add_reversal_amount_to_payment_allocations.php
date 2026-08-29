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

    public function up(): void
    {
        Schema::connection('tenant')->table('payment_allocations', function (Blueprint $table) {
            $table->decimal('reversed_amount', 12, 2)->default(0.00)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('payment_allocations', function (Blueprint $table) {
            $table->dropColumn('reversed_amount');
        });
    }
};
