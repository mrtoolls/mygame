<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('coin_cutover_logs')) return;

        Schema::create('coin_cutover_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 20); // convert, reset_test
            $table->boolean('dry_run')->default(false);
            $table->unsignedBigInteger('admin_id');
            $table->json('summary');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_cutover_logs');
    }
};
