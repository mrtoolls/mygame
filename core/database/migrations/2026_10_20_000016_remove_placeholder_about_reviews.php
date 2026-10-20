<?php

use App\Models\Frontend;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Frontend::where('data_keys', 'about_reviews.element')
            ->get()
            ->each(function (Frontend $element) {
                $values = $element->data_values;
                $name = trim((string) ($values->name ?? ''));
                $playerSince = trim((string) ($values->player_since ?? ''));
                $quote = trim((string) ($values->quote ?? ''));

                if (
                    $name === 'Player Story'
                    && $playerSince === 'Add membership year'
                    && str_starts_with($quote, 'Add an approved player review here.')
                ) {
                    $element->delete();
                }
            });
    }

    public function down(): void
    {
        // Placeholder sample rows intentionally not restored.
    }
};
