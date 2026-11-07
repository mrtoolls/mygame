<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('conversation_messages') || Schema::hasColumn('conversation_messages', 'kind')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->string('kind', 10)->default('text')->after('body');
            $table->string('attachment_path', 512)->nullable()->after('kind');
            $table->string('attachment_name', 255)->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('conversation_messages') || !Schema::hasColumn('conversation_messages', 'kind')) {
            return;
        }

        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
        });
    }
};
