<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admin_impersonations')) {
            Schema::create('admin_impersonations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('started_at');
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
                $table->index(['admin_id', 'started_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_impersonations');
    }
};
