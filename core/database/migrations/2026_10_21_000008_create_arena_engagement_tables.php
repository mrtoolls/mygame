<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('arena_comments')) {
            Schema::create('arena_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('parent_id')->nullable(); // one level of replies only
                $table->text('body');
                $table->timestamps();

                $table->foreign('post_id')->references('id')->on('arena_posts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('parent_id')->references('id')->on('arena_comments')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('arena_post_votes')) {
            Schema::create('arena_post_votes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('post_id');
                $table->unsignedBigInteger('user_id');
                $table->tinyInteger('value'); // 1 or -1
                $table->timestamps();

                $table->foreign('post_id')->references('id')->on('arena_posts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['post_id', 'user_id']);
            });
        }

        if (!Schema::hasTable('arena_reactions')) {
            Schema::create('arena_reactions', function (Blueprint $table) {
                $table->id();
                $table->string('reactable_type', 20); // 'post' or 'comment'
                $table->unsignedBigInteger('reactable_id');
                $table->unsignedBigInteger('user_id');
                $table->string('emoji', 10);
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->unique(['reactable_type', 'reactable_id', 'user_id']);
                $table->index(['reactable_type', 'reactable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_reactions');
        Schema::dropIfExists('arena_post_votes');
        Schema::dropIfExists('arena_comments');
    }
};
