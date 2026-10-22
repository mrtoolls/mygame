<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('competition_tickets')) {
            Schema::create('competition_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('ticket_count');
                $table->timestamp('frozen_at');
                $table->timestamps();
                $table->unique(['competition_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('competition_draws')) {
            Schema::create('competition_draws', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
                $table->foreignId('winner_user_id')->constrained('users')->restrictOnDelete();
                $table->unsignedInteger('draw_position');
                $table->json('seed_info'); // server seed hash, client seed, nonce - fairness proof
                $table->timestamp('drawn_at');
                $table->unsignedBigInteger('drawn_by')->nullable();
                $table->timestamps();
                $table->unique(['competition_id', 'winner_user_id']);
                $table->unique(['competition_id', 'draw_position']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_draws');
        Schema::dropIfExists('competition_tickets');
    }
};
