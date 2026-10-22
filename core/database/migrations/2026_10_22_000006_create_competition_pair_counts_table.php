<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('competition_pair_counts')) {
            return;
        }

        Schema::create('competition_pair_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            // Always stored low/high (user_id_low < user_id_high) so each opponent pair has
            // exactly one row regardless of who counted the game first.
            $table->unsignedBigInteger('user_id_low');
            $table->unsignedBigInteger('user_id_high');
            $table->unsignedInteger('games_count')->default(0);
            $table->timestamps();

            $table->unique(['competition_id', 'user_id_low', 'user_id_high'], 'comp_pair_counts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_pair_counts');
    }
};
