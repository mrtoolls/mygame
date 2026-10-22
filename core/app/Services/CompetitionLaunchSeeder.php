<?php

namespace App\Services;

use App\Games\TableGames\TableGameRules;
use App\Models\Competition;
use App\Models\CompetitionAuditLog;
use App\Models\CompetitionPrize;
use Illuminate\Support\Facades\DB;

/**
 * One-time launch data for the five November 2026 competitions. Idempotent by slug - never
 * updates or touches a competition that already exists, so an admin's later edits in the
 * Competitions admin screen are always safe from this running again.
 */
class CompetitionLaunchSeeder
{
    private const START_AT = '2026-11-01 00:00:00';
    private const END_AT = '2026-11-30 23:59:59';

    // Dates are stored as plain wall-clock strings with no explicit UTC conversion, matching
    // this codebase's existing convention for admin-entered datetimes (e.g. ArenaAnnouncement's
    // ends_at is assigned straight from request input) - Carbon reads/writes them consistently
    // against whatever timezone config/app.php currently resolves to.
    public function definitions(): array
    {
        $tail = "\n\nAccounts involved in collusion, multi-accounting or abuse are disqualified. "
            . "Prizes are paid manually after review, normally within 7 days of the end date, to your "
            . "wallet balance (converted to your wallet currency at the rate on payout day). Winxpot's "
            . "decisions are final. Competitions may be changed or cancelled if abuse is detected. "
            . "Players must be 18+ with a verified account; banned or flagged accounts are excluded.";

        $definitions = [
            [
                'slug' => 'ludo-league-nov-2026',
                'title' => 'Ludo League: November 2026',
                'type' => Competition::TYPE_TABLE_LEAGUE,
                'eligible_games' => ['ludo'],
                'min_bet_usd' => 0.50,
                'min_games' => 10,
                'min_distinct_opponents' => 5,
                'max_games_per_opponent_pair' => 3,
                // NOTE: the engine only distinguishes win/draw/loss per player in a settled game,
                // not finishing rank (Ludo settlement credits exactly one winner, everyone else
                // is "loss") - a win is scored as the requested 1st-place value; see the report
                // for why 2nd/3rd/4th-specific points could not be implemented as asked.
                'type_settings' => ['points' => ['win' => 3, 'draw' => 0, 'loss' => 0]],
                'description' => 'Climb the Ludo table. Play real opponents, win games and earn league points all month. The top five players share the prize pool.',
                'rules' => "1. Play Ludo with stakes of at least \$0.50 during the competition period.\n"
                    . "2. Points per finished game: a win earns 3 points.\n"
                    . "3. To be prize-eligible you need at least 10 counted games against at least 5 different opponents.\n"
                    . "4. Only the first 3 games between the same two players earn full points; further games between them earn reduced or zero points.\n"
                    . "5. Forfeited, expired, cancelled or refunded games do not count.\n"
                    . "6. Ties are broken by win rate, then number of different opponents, then who reached their score first."
                    . $tail,
                'prizes' => [1 => 100, 2 => 50, 3 => 25, 4 => 15, 5 => 10],
            ],
            [
                'slug' => 'wager-race-nov-2026',
                'title' => 'Wager Race: November 2026',
                'type' => Competition::TYPE_WAGER_RACE,
                'eligible_games' => null,
                'min_bet_usd' => 0.50,
                'min_games' => 10,
                'min_distinct_opponents' => 3,
                'max_games_per_opponent_pair' => 5,
                'type_settings' => null,
                'description' => 'The most active players on the table games win. Play as many games as you like against different opponents and top the race.',
                'rules' => "1. Ranking is total USD wagered on settled table games, with each stake converted at the rate locked when you joined.\n"
                    . "2. Only the first 5 games between the same two players count toward your total; further games between them count for reduced or zero value.\n"
                    . "3. To be prize-eligible you need at least 10 counted games against at least 3 different opponents.\n"
                    . "4. Forfeited, expired, cancelled or refunded games do not count.\n"
                    . "5. Ties are broken by whoever reached their total first."
                    . $tail,
                'prizes' => [1 => 75, 2 => 40, 3 => 20],
            ],
            [
                'slug' => 'lucky-draw-nov-2026',
                'title' => 'Lucky Draw: November 2026',
                'type' => Competition::TYPE_LUCKY_DRAW,
                'eligible_games' => null,
                'min_bet_usd' => 0.50,
                'max_games_per_opponent_pair' => 5,
                'ticket_usd_per_ticket' => 10,
                'max_tickets_per_user' => 100,
                'type_settings' => null,
                'description' => 'Every $10 you play earns a ticket. Five winners are drawn at random after the month ends. More games, more chances.',
                'rules' => "1. Tickets equal your counted USD wagered divided by \$10, rounded down, up to a maximum of 100 tickets per player.\n"
                    . "2. Only the first 5 games between the same two players count toward your ticket total; further games between them count for reduced or zero value.\n"
                    . "3. The draw happens after the competition ends, using a secure random draw from frozen ticket totals.\n"
                    . "4. Each player can win once. Results and the draw time are shown publicly.\n"
                    . "5. Forfeited, expired, cancelled or refunded games do not count."
                    . $tail,
                'prizes' => [1 => 20, 2 => 20, 3 => 20, 4 => 20, 5 => 20],
            ],
            [
                'slug' => 'biggest-win-nov-2026',
                'title' => 'Biggest Win: November 2026',
                'type' => Competition::TYPE_BIGGEST_WIN,
                'eligible_games' => null,
                // NOTE: stored for reference/rules copy only - the current biggest_win ranking
                // query (CompetitionService::rankBiggestWin) does not read min_bet_usd at all,
                // since game_logs has no historical FX rate-lock to check it against. See report.
                'min_bet_usd' => 1.00,
                'type_settings' => null,
                'description' => 'Hit the biggest multiplier on a single slot round this month and take the top prize.',
                'rules' => "1. Ranking is the highest single-round win multiplier on eligible slot rounds with stakes of at least \$1.\n"
                    . "2. Ties go to whoever reached the multiplier first.\n"
                    . "3. Demo play does not count."
                    . $tail,
                'prizes' => [1 => 75, 2 => 40, 3 => 20],
            ],
            [
                'slug' => 'chess-league-nov-2026',
                'title' => 'Chess League: November 2026',
                'type' => Competition::TYPE_TABLE_LEAGUE,
                'eligible_games' => ['chess'],
                'min_bet_usd' => 0.50,
                'min_games' => 10,
                'min_distinct_opponents' => 5,
                'max_games_per_opponent_pair' => 3,
                'type_settings' => ['points' => ['win' => 3, 'draw' => 1, 'loss' => 0]],
                'description' => 'Climb the Chess table. Play real opponents, win games and earn league points all month. The top three players share the prize pool.',
                'rules' => "1. Play Chess with stakes of at least \$0.50 during the competition period.\n"
                    . "2. Points per finished game: a win earns 3 points, a draw earns 1 point, a loss earns 0.\n"
                    . "3. To be prize-eligible you need at least 10 counted games against at least 5 different opponents.\n"
                    . "4. Only the first 3 games between the same two players earn full points; further games between them earn reduced or zero points.\n"
                    . "5. Forfeited, expired, cancelled or refunded games do not count.\n"
                    . "6. Ties are broken by win rate, then number of different opponents, then who reached their score first."
                    . $tail,
                'prizes' => [1 => 100, 2 => 50, 3 => 25],
            ],
        ];

        if (!in_array('chess', TableGameRules::AVAILABLE, true)) {
            $definitions = array_values(array_filter($definitions, fn ($row) => $row['slug'] !== 'chess-league-nov-2026'));
        }

        return $definitions;
    }

    public function seed(?int $adminId): array
    {
        $created = [];
        $skipped = [];

        foreach ($this->definitions() as $definition) {
            if (Competition::where('slug', $definition['slug'])->exists()) {
                $skipped[] = $definition['title'];
                continue;
            }

            DB::transaction(function () use ($definition, $adminId) {
                $competition = Competition::create([
                    'title' => $definition['title'],
                    'slug' => $definition['slug'],
                    'type' => $definition['type'],
                    'description' => $definition['description'],
                    'rules' => $definition['rules'],
                    'banner_image' => null,
                    'status' => Competition::STATUS_SCHEDULED,
                    'start_at' => self::START_AT,
                    'end_at' => self::END_AT,
                    'grace_minutes' => 30,
                    'min_bet_usd' => $definition['min_bet_usd'] ?? null,
                    'eligible_games' => $definition['eligible_games'] ?? null,
                    'min_games' => $definition['min_games'] ?? null,
                    'min_distinct_opponents' => $definition['min_distinct_opponents'] ?? null,
                    'max_games_per_opponent_pair' => $definition['max_games_per_opponent_pair'] ?? null,
                    'ticket_usd_per_ticket' => $definition['ticket_usd_per_ticket'] ?? null,
                    'max_tickets_per_user' => $definition['max_tickets_per_user'] ?? null,
                    'type_settings' => $definition['type_settings'] ?? null,
                    'created_by' => $adminId,
                ]);

                foreach ($definition['prizes'] as $rank => $amount) {
                    CompetitionPrize::create([
                        'competition_id' => $competition->id,
                        'rank' => $rank,
                        'amount_usd' => $amount,
                    ]);
                }

                CompetitionAuditLog::create([
                    'competition_id' => $competition->id,
                    'admin_id' => $adminId,
                    'action' => 'launch_seeded',
                    'details' => ['slug' => $competition->slug, 'title' => $competition->title],
                ]);
            });

            $created[] = $definition['title'];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function allSlugsExist(): bool
    {
        $slugs = array_column($this->definitions(), 'slug');
        if (!$slugs) {
            return true;
        }
        return Competition::whereIn('slug', $slugs)->count() === count($slugs);
    }
}
