<?php

use App\Models\Frontend;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Frontend::where('data_keys', 'sponsorships.content')
            ->get()
            ->each(function (Frontend $content) {
                $values = $content->data_values;
                if (($values->subheading ?? null) === 'Proud partners across sport and esports') {
                    $values->subheading = 'Brands and partners supporting Winxpot events';
                    $content->data_values = $values;
                    $content->save();
                }
            });
    }

    public function down(): void
    {
        // Intentionally not reversed - the old sports/esports copy was inaccurate.
    }
};
