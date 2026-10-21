<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('arena_announcements')) {
            Schema::create('arena_announcements', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('badge_label')->nullable(); // e.g. "Tournament"
                $table->string('reward_label')->nullable(); // e.g. "$500 prize pool"
                $table->string('image')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->string('button_label')->nullable();
                $table->string('button_url')->nullable();
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('arena_announcements');
    }
};
