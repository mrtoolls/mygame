<?php

use App\Models\Gift;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Only replaces a gift's image if it still points at the original placeholder SVG seeded
    // by 2026_10_20_000011_seed_default_gifts.php - an admin who already uploaded their own
    // artwork for one of these slugs (Admin -> Gifts) is never overwritten.
    public function up(): void
    {
        if (!Schema::hasTable('gifts')) return;

        $defaults = ['fox', 'rose', 'heart', 'crown', 'lion', 'diamond'];

        foreach ($defaults as $slug) {
            Gift::where('slug', $slug)
                ->where('image', 'assets/images/gift/' . $slug . '.svg')
                ->update(['image' => "assets/images/frontend/nockturne_assets/{$slug}-512.png"]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('gifts')) return;

        $defaults = ['fox', 'rose', 'heart', 'crown', 'lion', 'diamond'];

        foreach ($defaults as $slug) {
            Gift::where('slug', $slug)
                ->where('image', "assets/images/frontend/nockturne_assets/{$slug}-512.png")
                ->update(['image' => 'assets/images/gift/' . $slug . '.svg']);
        }
    }
};
