<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('gender', 10)->nullable()->after('username');
                $table->string('image')->nullable()->after('gender');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'gender')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['gender', 'image']);
            });
        }
    }
};
