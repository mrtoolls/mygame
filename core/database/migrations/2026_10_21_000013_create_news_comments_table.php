<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('news_comments')) {
            Schema::create('news_comments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('news_post_id');
                $table->unsignedBigInteger('user_id');
                $table->text('body');
                $table->timestamps();

                $table->foreign('news_post_id')->references('id')->on('news_posts')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_comments');
    }
};
