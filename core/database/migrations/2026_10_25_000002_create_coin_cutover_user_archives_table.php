<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row per user converted by a cutover run: exactly what their legacy balance was, in which
// currency, at what coin rate, and how many coins that minted - kept forever as the audit trail
// for "where did this user's coin balance come from", separate from coin_cutover_logs (one row
// per RUN, a JSON summary) and from the plain Transaction row CoinCutover already writes (human-
// readable history, not a queryable/structured archive).
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('coin_cutover_user_archives')) {
            Schema::create('coin_cutover_user_archives', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('coin_cutover_log_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->decimal('legacy_balance', 28, 8);
                $table->unsignedBigInteger('currency_id')->nullable();
                $table->decimal('rate_used', 28, 8)->nullable();
                $table->decimal('coins_minted', 28, 8);
                $table->timestamp('created_at')->useCurrent();

                $table->index('user_id');
                $table->index('coin_cutover_log_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_cutover_user_archives');
    }
};
