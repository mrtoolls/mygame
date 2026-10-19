<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('business_promotion_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('daily_price_minor')->default(0);
            $table->unsignedBigInteger('weekly_price_minor')->default(0);
            $table->unsignedBigInteger('monthly_price_minor')->default(0);
            $table->timestamps();
            $table->unique('currency_id');
        });

        Schema::create('business_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->string('business_name', 120);
            $table->string('title', 160);
            $table->text('description');
            $table->string('image');
            $table->string('destination_url', 2048);
            $table->enum('duration', ['day', 'week', 'month']);
            $table->unsignedBigInteger('amount_minor');
            $table->string('payment_reference', 64)->unique();
            $table->enum('status', ['pending', 'active', 'rejected', 'expired'])->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'ends_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_promotions');
        Schema::dropIfExists('business_promotion_rates');
    }
};
