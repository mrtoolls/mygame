<?php

use App\Models\Frontend;
use App\Models\GeneralSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Combines the earlier placeholder-delete migration with marking "about_reviews.element" as
     * already seeded in the new nocturne_seeded_elements tracker (see
     * 2026_10_22_000002_add_nocturne_seeded_elements_to_general_settings.php and the matching
     * SystemController::seedNocturnePages() fix) - without this, clicking the admin's "Seed /
     * Refresh Nocturne Pages" button after the delete would just find zero rows for that key and
     * silently insert the same 3 sample placeholder cards right back in, which is what happened
     * the first time this was attempted.
     */
    public function up(): void
    {
        if (Schema::hasTable('frontends')) {
            Frontend::where('data_keys', 'about_reviews.element')->get()->each(function (Frontend $row) {
                $quote = (string) ($row->data_values->quote ?? '');
                $name = (string) ($row->data_values->name ?? '');
                if (str_contains($quote, 'Add an approved player review') || trim($name) === 'Player Story') {
                    $row->delete();
                }
            });
        }

        if (Schema::hasTable('general_settings') && Schema::hasColumn('general_settings', 'nocturne_seeded_elements')) {
            $settings = GeneralSetting::first();
            if ($settings) {
                $seeded = array_filter(explode(',', (string) $settings->nocturne_seeded_elements));
                // Backfill every known repeatable-element section as "already seeded" based on
                // whether it currently has rows - this preserves the old exists-check's effect
                // for sections nobody touched, so switching SystemController::seedNocturnePages()
                // over to the new tracker doesn't cause it to insert a second, duplicate batch of
                // rows into those sections the next time "Seed / Refresh" runs. about_reviews is
                // force-included even though its rows were just deleted above - that is the
                // actual point of this migration.
                $knownElementKeys = ['licenses.element', 'about_story.element', 'about_team.element', 'about_awards.element', 'about_reviews.element', 'about_statistics.element', 'about_links.element', 'about_news.element', 'vip_member_privileges.element', 'vip_member_faq.element'];
                foreach ($knownElementKeys as $key) {
                    if (in_array($key, $seeded, true)) continue;
                    if ($key === 'about_reviews.element' || (Schema::hasTable('frontends') && Frontend::where('data_keys', $key)->exists())) {
                        $seeded[] = $key;
                    }
                }
                $settings->nocturne_seeded_elements = implode(',', array_unique($seeded));
                $settings->save();
            }
        }
    }

    public function down(): void
    {
        // Intentionally not reversible.
    }
};
