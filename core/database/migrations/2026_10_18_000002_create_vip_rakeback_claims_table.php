<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vip_rakeback_claims', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('vip_tier_id')->nullable();
            $table->decimal('amount', 28, 8);
            $table->decimal('total_wagered_snapshot', 28, 8);
            $table->string('trx')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vip_tier_id')->references('id')->on('vip_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_rakeback_claims');
    }
};
