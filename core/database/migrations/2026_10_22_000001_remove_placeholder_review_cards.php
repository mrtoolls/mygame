<?php

use App\Models\Frontend;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The "about_reviews.element" cards on the Feedback page are admin-managed CMS content
     * (Frontend rows), separate from real player feedback (user_feedback table). A handful of
     * these still hold the template's default placeholder copy ("Player Story" / "Add an
     * approved player review here...") that was never replaced with real testimonials.
     */
    public function up(): void
    {
        Frontend::where('data_keys', 'about_reviews.element')->get()->each(function (Frontend $row) {
            $quote = (string) ($row->data_values->quote ?? '');
            $name = (string) ($row->data_values->name ?? '');
            if (str_contains($quote, 'Add an approved player review') || $name === 'Player Story') {
                $row->delete();
            }
        });
    }

    public function down(): void
    {
        // Intentionally not reversible - placeholder rows are not worth restoring.
    }
};
