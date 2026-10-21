<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\GeneralSetting;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('general_settings', 'chatbot_name')) {
                    $table->string('chatbot_name')->nullable();
                }
                if (!Schema::hasColumn('general_settings', 'chatbot_photo')) {
                    $table->string('chatbot_photo')->nullable();
                }
            });

            $settings = GeneralSetting::first();
            if ($settings && empty($settings->chatbot_name)) {
                $settings->chatbot_name = 'Lilly';
                $settings->save();
            }
        }

        if (!Schema::hasTable('support_bot_trainings')) {
            Schema::create('support_bot_trainings', function (Blueprint $table) {
                $table->id();
                $table->string('trigger_phrases', 1000); // comma-separated keywords/phrases to match on
                $table->text('response');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_bot_trainings');
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table) {
                if (Schema::hasColumn('general_settings', 'chatbot_photo')) {
                    $table->dropColumn('chatbot_photo');
                }
                if (Schema::hasColumn('general_settings', 'chatbot_name')) {
                    $table->dropColumn('chatbot_name');
                }
            });
        }
    }
};
