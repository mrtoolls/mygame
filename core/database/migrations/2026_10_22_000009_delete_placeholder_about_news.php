<?php

use App\Models\Frontend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same placeholder-cleanup as 2026_10_22_000003_delete_and_lock_placeholder_reviews.php, for
     * the About Us "Latest News" cards this time. about_news.element was already marked as
     * "seeded" in the nocturne_seeded_elements tracker by that earlier migration (it existed at
     * the time), so deleting these rows here is safe - the admin's "Seed / Refresh Nocturne
     * Pages" button will not insert them again.
     */
    public function up(): void
    {
        if (!Schema::hasTable('frontends')) return;

        $placeholderTitles = [
            'A Fresh Look at Your Gaming Experience',
            'Discover Games for Every Mood',
            'Stay Close to the Action',
        ];

        Frontend::where('data_keys', 'about_news.element')->get()->each(function (Frontend $row) use ($placeholderTitles) {
            $title = trim((string) ($row->data_values->title ?? ''));
            if (in_array($title, $placeholderTitles, true)) {
                $row->delete();
            }
        });
    }

    public function down(): void
    {
        // Intentionally not reversible.
    }
};
