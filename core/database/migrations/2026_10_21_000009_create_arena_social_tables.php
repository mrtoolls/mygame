<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('arena_follows')) {
            Schema::create('arena_follows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('follower_id');
                $table->unsignedBigInteger('followed_id');
                $table->timestamps();

                $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('followed_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['follower_id', 'followed_id']);
            });
        }

        if (!Schema::hasTable('arena_notifications')) {
            Schema::create('arena_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id'); // recipient
                $table->string('type', 20); // comment|reply|reaction|follow|post_approved|post_rejected
                $table->unsignedBigInteger('actor_id')->nullable(); // who triggered it (null for system/moderation events)
                $table->string('subject_type', 20)->nullable(); // post|comment
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->string('message', 255);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
                $table->index(['user_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_notifications');
        Schema::dropIfExists('arena_follows');
    }
};
