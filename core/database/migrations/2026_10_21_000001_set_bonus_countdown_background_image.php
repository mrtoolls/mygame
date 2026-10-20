<?php

use App\Models\Frontend;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('frontends')) return;

        $filename = 'sidebar-reward-giftboxes.png';
        $content = Frontend::where('data_keys', 'bonus_countdown.content')->first();

        if ($content) {
            $values = $content->data_values ?: new \stdClass();
            $values->background_image = $filename;
            $content->data_values = $values;
            $content->save();
            return;
        }

        $content = new Frontend();
        $content->data_keys = 'bonus_countdown.content';
        $content->tempname = activeTemplateName();
        $content->data_values = (object) ['background_image' => $filename];
        $content->save();
    }

    public function down(): void
    {
        if (!Schema::hasTable('frontends')) return;

        $content = Frontend::where('data_keys', 'bonus_countdown.content')->first();
        if ($content && $content->data_values) {
            $values = $content->data_values;
            unset($values->background_image);
            $content->data_values = $values;
            $content->save();
        }
    }
};
