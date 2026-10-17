<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vip_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('badge')->nullable();
            $table->decimal('min_wagered', 28, 8)->default(0);
            $table->decimal('bonus_amount', 28, 8)->default(0);
            $table->decimal('rakeback_percent', 5, 2)->default(0);
            $table->decimal('max_bet', 28, 8)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vip_tiers');
    }
};
