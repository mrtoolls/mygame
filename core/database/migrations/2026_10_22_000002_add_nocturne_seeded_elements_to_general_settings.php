<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SystemController::seedNocturnePages() decides whether to (re)seed each repeatable content
     * section ("about_reviews.element" and friends) by checking whether any row for that key
     * currently exists. That makes removing unwanted placeholder rows (e.g. the 3 sample review
     * cards) non-permanent: the very next time "Seed / Refresh Nocturne Pages" runs, it finds
     * zero rows for that key and seeds the placeholders right back in. Track which element
     * sections have ever been seeded once, independent of whether their rows still exist, so a
     * deliberate deletion sticks.
     */
    public function up(): void
    {
        if (Schema::hasTable('general_settings') && !Schema::hasColumn('general_settings', 'nocturne_seeded_elements')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->text('nocturne_seeded_elements')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'nocturne_seeded_elements')) {
            Schema::table('general_settings', function (Blueprint $table) {
                $table->dropColumn('nocturne_seeded_elements');
            });
        }
    }
};
