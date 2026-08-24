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
        Schema::connection('tenant')->table('lab_orders', function (Blueprint $table) {
            $table->uuid('parent_order_id')->nullable()->after('treatment_plan_item_id');
            $table->text('remake_reason')->nullable()->after('notes');
            $table->text('quality_check_notes')->nullable()->after('remake_reason');

            $table->foreign('parent_order_id')->references('id')->on('lab_orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('lab_orders', function (Blueprint $table) {
            $table->dropForeign(['parent_order_id']);
            $table->dropColumn(['parent_order_id', 'remake_reason', 'quality_check_notes']);
        });
    }
};
