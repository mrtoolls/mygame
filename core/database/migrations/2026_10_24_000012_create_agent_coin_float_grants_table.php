<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('agent_coin_float_grants')) return;

        Schema::create('agent_coin_float_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_agent_id');
            $table->decimal('amount', 28, 8);
            $table->text('reason');
            $table->unsignedBigInteger('requested_by_admin_id');
            $table->unsignedBigInteger('approved_by_admin_id')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('support_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_coin_float_grants');
    }
};
