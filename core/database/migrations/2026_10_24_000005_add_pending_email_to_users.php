<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A changed email is held here, unverified, until the user confirms it with a code sent to
    // the NEW address - the real `email` column (and `ev`) is only ever touched after that
    // confirmation, so an account can't be silently taken over by someone typing in an email
    // they don't control.
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'pending_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('pending_email')->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pending_email')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('pending_email');
            });
        }
    }
};
