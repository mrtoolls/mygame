<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live-chat and admin ticket-reply code has always assumed
 * support_tickets.admin_id exists (0 = unassigned), but on at least one
 * live install the column is missing, breaking every new ticket/live-chat
 * insert with a "Column not found: admin_id" SQL error. Self-heals it the
 * same way every other schema gap this project has hit has been handled.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('support_tickets') && !Schema::hasColumn('support_tickets', 'admin_id')) {
            Schema::table('support_tickets', function (Blueprint $table) {
                $table->unsignedBigInteger('admin_id')->default(0)->after('user_id');
            });
        }
    }

    public function down(): void
    {
        // Intentionally not reversible: other installs may genuinely rely on
        // this column already existing from the base schema, and dropping it
        // here could destroy data this migration never created.
    }
};
