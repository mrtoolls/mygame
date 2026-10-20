<?php

use App\Models\Gift;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('gifts') || Gift::count() > 0) return;

        // Default prices are a starting point - the admin can change them any time from
        // Admin -> Gifts. Cash-out value is set at a 30% platform margin below buy price.
        $defaults = [
            ['name' => 'Fox', 'file' => 'fox.svg', 'buy_price' => 1, 'sort' => 1],
            ['name' => 'Rose', 'file' => 'rose.svg', 'buy_price' => 2, 'sort' => 2],
            ['name' => 'Heart', 'file' => 'heart.svg', 'buy_price' => 5, 'sort' => 3],
            ['name' => 'Crown', 'file' => 'crown.svg', 'buy_price' => 10, 'sort' => 4],
            ['name' => 'Lion', 'file' => 'lion.svg', 'buy_price' => 20, 'sort' => 5],
            ['name' => 'Diamond', 'file' => 'diamond.svg', 'buy_price' => 50, 'sort' => 6],
        ];

        foreach ($defaults as $gift) {
            Gift::create([
                'name' => $gift['name'],
                'slug' => Str::slug($gift['name']),
                'image' => 'assets/images/gift/' . $gift['file'],
                'buy_price' => $gift['buy_price'],
                'cashout_value' => round($gift['buy_price'] * 0.7, 8),
                'sort_order' => $gift['sort'],
                'status' => 1,
            ]);
        }
    }

    public function down(): void
    {
        Gift::whereIn('slug', ['fox', 'rose', 'heart', 'crown', 'lion', 'diamond'])->delete();
    }
};
