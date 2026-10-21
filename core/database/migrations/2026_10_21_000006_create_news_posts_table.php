<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('news_posts')) {
            Schema::create('news_posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('badge_label')->nullable();
                $table->string('excerpt', 500)->nullable();
                $table->longText('body');
                $table->string('image')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->string('posted_by_type', 10)->default('admin'); // 'admin' or 'agent'
                $table->unsignedBigInteger('posted_by_id')->nullable(); // admins.id or support_agents.id
                $table->string('posted_by_name')->nullable(); // snapshot, survives the author being removed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
