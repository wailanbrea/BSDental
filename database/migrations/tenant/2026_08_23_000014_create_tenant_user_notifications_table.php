<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('type', 50)->default('system');
            $table->string('severity', 20)->default('info')->index();
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'read_at', 'created_at'], 'user_notifications_inbox_index');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('user_notifications');
    }
};
