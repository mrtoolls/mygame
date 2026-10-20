<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gifts')) {
            Schema::create('gifts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 60);
                $table->string('slug', 60)->unique();
                $table->string('image')->nullable();
                // Priced in USD - the same pivot currency TableGameService already uses
                // (Currency::rate_to_usd) to convert amounts between players' wallet currencies.
                $table->decimal('buy_price', 18, 8);
                $table->decimal('cashout_value', 18, 8);
                $table->unsignedInteger('sort_order')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_gift_inventory')) {
            Schema::create('user_gift_inventory', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('gift_id')->constrained('gifts')->cascadeOnDelete();
                $table->unsignedInteger('quantity')->default(0);
                $table->timestamps();
                $table->unique(['user_id', 'gift_id']);
            });
        }

        if (!Schema::hasTable('gift_transactions')) {
            Schema::create('gift_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('type', 12); // purchase | send | cashout
                $table->foreignId('gift_id')->constrained('gifts')->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('quantity');
                $table->decimal('unit_buy_price', 18, 8);
                $table->decimal('unit_cashout_value', 18, 8);
                $table->uuid('table_game_id')->nullable();
                $table->timestamps();
                $table->index(['from_user_id']);
                $table->index(['to_user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_transactions');
        Schema::dropIfExists('user_gift_inventory');
        Schema::dropIfExists('gifts');
    }
};
