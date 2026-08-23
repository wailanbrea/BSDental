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
        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            $table->uuid('patient_id')->nullable()->change();
            $table->string('prospect_first_name')->nullable()->after('patient_id');
            $table->string('prospect_last_name')->nullable()->after('prospect_first_name');
            $table->string('prospect_phone', 50)->nullable()->index()->after('prospect_last_name');
            $table->string('prospect_email')->nullable()->index()->after('prospect_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('quotes', function (Blueprint $table) {
            $table->dropIndex(['prospect_phone']);
            $table->dropIndex(['prospect_email']);
            $table->dropColumn([
                'prospect_first_name',
                'prospect_last_name',
                'prospect_phone',
                'prospect_email',
            ]);
            $table->uuid('patient_id')->nullable(false)->change();
        });
    }
};
