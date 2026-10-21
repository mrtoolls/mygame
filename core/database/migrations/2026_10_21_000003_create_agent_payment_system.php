<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_agents')) {
            Schema::table('support_agents', function (Blueprint $table) {
                if (!Schema::hasColumn('support_agents', 'accepted_payment_types')) {
                    $table->json('accepted_payment_types')->nullable();
                }
                if (!Schema::hasColumn('support_agents', 'is_verified')) {
                    $table->boolean('is_verified')->default(false);
                }
                if (!Schema::hasColumn('support_agents', 'speed_override')) {
                    $table->unsignedTinyInteger('speed_override')->nullable();
                }
            });
        }

        if (Schema::hasTable('deposits') && Schema::hasTable('support_agents') && !Schema::hasColumn('deposits', 'support_agent_id')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->foreignId('support_agent_id')->nullable()->after('user_id')
                    ->constrained('support_agents')->nullOnDelete();
            });
        }

        if (Schema::hasTable('support_agents') && Schema::hasTable('deposits') && Schema::hasTable('users') && !Schema::hasTable('agent_transaction_speed_logs')) {
            Schema::create('agent_transaction_speed_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_agent_id')->constrained('support_agents')->cascadeOnDelete();
                $table->foreignId('deposit_id')->unique()->constrained('deposits')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamp('confirmed_at');
                $table->unsignedSmallInteger('sla_minutes_snapshot');
                $table->unsignedInteger('elapsed_seconds');
                $table->boolean('met_fast_threshold')->default(false);
                $table->unsignedTinyInteger('user_rating')->nullable();
                $table->timestamp('rated_at')->nullable();
                $table->timestamps();
                $table->index(['support_agent_id', 'confirmed_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_transaction_speed_logs');

        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'support_agent_id')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropConstrainedForeignId('support_agent_id');
            });
        }

        if (Schema::hasTable('support_agents')) {
            Schema::table('support_agents', function (Blueprint $table) {
                foreach (['accepted_payment_types', 'is_verified', 'speed_override'] as $column) {
                    if (Schema::hasColumn('support_agents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
