<?php

use App\Games\TableGames\TableGameRules;
use App\Models\Frontend;
use App\Models\Game;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('frontends')) return;

        $row = Frontend::where('data_keys', 'about_statistics.element')
            ->get()
            ->first(fn ($item) => ($item->data_values->title ?? null) === 'Total Paid to Players');
        if (!$row) return;

        $gameCount = (Schema::hasTable('games') ? Game::active()->count() : 0) + count(TableGameRules::AVAILABLE);
        $row->data_values = (object) ['title' => 'Total Games Available', 'amount' => (string) $gameCount];
        $row->save();
    }

    public function down(): void
    {
        if (!Schema::hasTable('frontends')) return;
        $row = Frontend::where('data_keys', 'about_statistics.element')
            ->get()
            ->first(fn ($item) => ($item->data_values->title ?? null) === 'Total Games Available');
        if ($row) {
            $row->data_values = (object) ['title' => 'Total Paid to Players', 'amount' => '—'];
            $row->save();
        }
    }
};
