<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_presence')) {
            Schema::create('user_presence', function (Blueprint $table) {
                $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_seen_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_presence');
    }
};
