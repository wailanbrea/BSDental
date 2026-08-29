<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'tenant';

    public function up(): void
    {
        Schema::connection('tenant')->table('payment_allocations', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('reversed_amount');
            $table->string('idempotency_key', 120)->nullable()->unique()->after('reason');
            $table->uuid('created_by_user_id')->nullable()->index()->after('allocated_at');
        });

        $database = DB::connection('tenant');
        $permissionId = $database->table('permissions')
            ->where('name', 'payments.allocate')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = $database->table('permissions')->insertGetId([
                'name' => 'payments.allocate',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = $database->table('roles')
            ->whereIn('name', ['Owner', 'ClinicDirector', 'Cashier'])
            ->where('guard_name', 'web')
            ->pluck('id');

        $database->table('role_has_permissions')->insertOrIgnore(
            $roleIds->map(fn (int $roleId): array => [
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ])->all(),
        );
    }

    public function down(): void
    {
        $database = DB::connection('tenant');
        $permissionId = $database->table('permissions')
            ->where('name', 'payments.allocate')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId !== null) {
            $database->table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->delete();

            $database->table('permissions')
                ->where('id', $permissionId)
                ->delete();
        }

        Schema::connection('tenant')->table('payment_allocations', function (Blueprint $table) {
            $table->dropIndex(['created_by_user_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['reason', 'idempotency_key', 'created_by_user_id']);
        });
    }
};
