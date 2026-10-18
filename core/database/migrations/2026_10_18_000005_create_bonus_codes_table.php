<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bonus_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('amount', 28, 8);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('bonus_code_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('bonus_code_id');
            $table->decimal('amount', 28, 8);
            $table->string('trx')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('bonus_code_id')->references('id')->on('bonus_codes')->cascadeOnDelete();
            $table->unique(['user_id', 'bonus_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_code_redemptions');
        Schema::dropIfExists('bonus_codes');
    }
};
