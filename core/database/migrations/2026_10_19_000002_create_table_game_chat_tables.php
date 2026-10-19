<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_game_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('table_game_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 12)->default('text');
            $table->text('body')->nullable();
            $table->string('attachment_path', 512)->nullable();
            $table->string('attachment_name', 255)->nullable();
            $table->string('attachment_mime', 120)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->timestamps();
            $table->foreign('table_game_id')->references('id')->on('table_games')->cascadeOnDelete();
            $table->index(['table_game_id', 'id']);
        });

        Schema::create('table_game_message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('table_game_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['message_id', 'user_id']);
            $table->index(['message_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_game_message_reactions');
        Schema::dropIfExists('table_game_messages');
    }
};
