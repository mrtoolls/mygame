<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2026_10_24_000001 seeded the 5 platforms with is_active=false, requiring an admin to both
     * add a URL and separately flip it active - per request, a platform should go live as soon
     * as its URL is set, with no extra step. Sets is_active=true on every existing row
     * (scopeActive() already requires a non-empty url too, so a still-empty platform stays
     * invisible regardless of this flag - this only removes the redundant second step for ones
     * that do get a URL).
     */
    public function up(): void
    {
        if (!Schema::hasTable('social_links')) return;
        DB::table('social_links')->update(['is_active' => true]);
    }

    public function down(): void
    {
        // Not reversible - an admin may have deliberately deactivated a specific platform since
        // this ran, and a blind flip back would undo that real choice.
    }
};
