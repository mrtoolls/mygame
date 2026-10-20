<?php

namespace App\Http\Controllers;

use App\Games\TableGames\TableGameRules;
use App\Models\Game;
use App\Models\User;
use App\Models\UserPresence;
use Illuminate\Support\Facades\Schema;

class StatsController extends Controller
{
    // Public counters shown on the About Us page start from these floors and climb as real
    // activity happens, rather than showing the real (much smaller, early-launch) numbers bare.
    private const ONLINE_FLOOR = 18040;
    private const REGISTERED_FLOOR = 42000000;

    public function live()
    {
        $registered = self::REGISTERED_FLOOR + User::where('email', 'not like', '%@demo.winxpot.test')->count();

        $online = self::ONLINE_FLOOR;
        if (Schema::hasTable('user_presence')) {
            $online += UserPresence::where('last_seen_at', '>=', now()->subMinutes(5))
                ->whereHas('user', fn ($query) => $query->where('email', 'not like', '%@demo.winxpot.test'))
                ->count();
        }

        $games = count(TableGameRules::AVAILABLE) + (Schema::hasTable('games') ? Game::active()->count() : 0);

        return response()->json([
            'online' => $online,
            'registered' => $registered,
            'games' => $games,
        ]);
    }
}
