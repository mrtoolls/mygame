<?php

use App\Models\Competition;
use App\Models\CompetitionPrize;
use App\Models\CompetitionResult;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Accounts used by the Arena community seed. Match by username so imports can use new IDs. */
    private const USERNAMES = [
        'aliza', 'emersonwhiteee', 'ayguitar', 'mrleegan', 'jamesw', 'jennifern', 'davidk',
        'elizabethj', 'roberto', 'jessicad', 'danielm', 'karenm', 'thomaso', 'lisao', 'kwameb',
        'amarat', 'emekaa', 'funmip', 'femid', 'amaa', 'kojom', 'thandiwew', 'thabom', 'gracem',
        'kevinb', 'joys', 'marke', 'patiencek', 'ibrahimj', 'maryw', 'michaeln', 'lindak', 'johnj',
        'susano', 'williamd', 'sarahm', 'josephm', 'nancyo', 'christophero', 'ngozib', 'chidit',
        'chiamakaa', 'tundep', 'adaezed', 'kofia', 'abenam', 'siphow', 'nomvulam', 'brianm',
        'faithb', 'peters', 'mercye', 'stevenk', 'blessingj', 'msdammy', 'vicky', 'ademi',
    ];

    public function up(): void
    {
        foreach (['competitions', 'competition_prizes', 'competition_results'] as $table) {
            if (!Schema::hasTable($table)) return;
        }

        $users = User::query()->whereIn('username', self::USERNAMES)->where('status', 1)
            ->orderBy('id')->limit(12)->get(['id', 'username'])->values();
        if ($users->count() < 3) return;

        $now = Carbon::now();
        $events = [
            [
                'slug' => 'community-ludo-cup-sep-2026', 'title' => 'Community Ludo Cup · September 2026',
                'game' => 'ludo', 'start_days' => 55, 'end_days' => 43, 'points' => [15, 12, 9, 8, 7, 6, 5, 4, 3, 2, 1, 1],
                'prizes' => [100, 50, 25], 'description' => 'A month of friendly Ludo matches, close finishes and community competition.',
            ],
            [
                'slug' => 'whot-wager-race-sep-2026', 'title' => 'Whot Table Race · September 2026',
                'game' => 'whot', 'start_days' => 39, 'end_days' => 27, 'points' => [1280, 1140, 970, 840, 730, 620, 510, 430, 350, 270, 190, 120],
                'prizes' => [75, 40, 20], 'description' => 'Players went head to head in the September Whot table race.',
            ],
            [
                'slug' => 'chess-community-open-oct-2026', 'title' => 'Chess Community Open · October 2026',
                'game' => 'chess', 'start_days' => 22, 'end_days' => 8, 'points' => [27, 24, 21, 18, 15, 13, 11, 9, 7, 5, 3, 1],
                'prizes' => [120, 60, 30], 'description' => 'A two-week chess open featuring the community’s active players.',
            ],
        ];

        foreach ($events as $event) {
            if (Competition::where('slug', $event['slug'])->exists()) continue;

            $start = $now->copy()->subDays($event['start_days'])->startOfDay();
            $end = $now->copy()->subDays($event['end_days'])->endOfDay();
            DB::transaction(function () use ($event, $users, $start, $end) {
                $competition = Competition::create([
                    'title' => $event['title'],
                    'slug' => $event['slug'],
                    'type' => Competition::TYPE_TABLE_LEAGUE,
                    'description' => $event['description'],
                    'rules' => 'This completed community event used seeded showcase standings based on active community accounts. No wallet credits or prize payments were created by this seed.',
                    'banner_image' => null,
                    'status' => Competition::STATUS_FINALIZED,
                    'start_at' => $start,
                    'end_at' => $end,
                    'grace_minutes' => 30,
                    'eligible_games' => [$event['game']],
                    'min_games' => 0,
                    'min_distinct_opponents' => 0,
                    'max_games_per_opponent_pair' => 3,
                    'type_settings' => ['points' => ['win' => 3, 'draw' => 1, 'loss' => 0]],
                    'finalized_at' => $end->copy()->addMinutes(30),
                ]);

                foreach ($event['prizes'] as $index => $amount) {
                    CompetitionPrize::create([
                        'competition_id' => $competition->id,
                        'rank' => $index + 1,
                        'amount_usd' => $amount,
                    ]);
                }

                foreach ($users as $index => $user) {
                    CompetitionResult::create([
                        'competition_id' => $competition->id,
                        'user_id' => $user->id,
                        'rank' => $index + 1,
                        'metric_value' => $event['points'][$index],
                        'metric_label' => 'points',
                        // Showcase winners are not recorded as paid; this seed never issues money.
                        'prize_amount_usd' => null,
                        'source_ref' => 'community_showcase_seed:' . $event['slug'],
                        'created_at' => $end->copy()->addMinutes(30),
                        'updated_at' => $end->copy()->addMinutes(30),
                    ]);
                }

                if (Schema::hasTable('competition_audit_log')) {
                    DB::table('competition_audit_log')->insert([
                        'competition_id' => $competition->id,
                        'admin_id' => null,
                        'action' => 'community_showcase_seeded',
                        'details' => json_encode(['slug' => $competition->slug, 'active_users' => $users->count(), 'no_payouts' => true]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        // Keep completed showcase rows: users may have seen or bookmarked the public results.
    }
};
