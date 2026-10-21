<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('agent_notifications')) {
            Schema::create('agent_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('recipient_type', 10);
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->foreignId('deposit_id')->nullable()->constrained('deposits')->cascadeOnDelete();
                $table->string('title');
                $table->string('body', 500)->nullable();
                $table->string('click_url')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['recipient_type', 'recipient_id', 'read_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_notifications');
    }
};
