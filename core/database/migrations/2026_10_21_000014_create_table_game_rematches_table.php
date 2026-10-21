<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('table_game_rematches') || !Schema::hasTable('table_games')) {
            return;
        }

        Schema::create('table_game_rematches', function (Blueprint $table) {
            $table->id();
            $table->uuid('original_table_game_id');
            $table->uuid('new_table_game_id')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique('original_table_game_id');
            $table->foreign('original_table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->foreign('new_table_game_id')->references('id')->on('table_games')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_game_rematches');
    }
};
