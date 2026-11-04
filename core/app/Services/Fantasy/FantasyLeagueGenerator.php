<?php

namespace App\Services\Fantasy;

use App\Models\FantasyClub;
use App\Models\FantasyFixture;
use App\Models\FantasyGameweek;
use App\Models\FantasyLeague;
use App\Models\FantasyPlayer;
use App\Models\FantasySeason;
use App\Support\FantasyNames;
use Illuminate\Support\Facades\DB;

/**
 * The admin "Generate league" tool: 20 fictional clubs with 24-26 fictional players each, a full
 * double round-robin season (19 clubs x 2 = 38 gameweeks of 10 fixtures each = 380 fixtures,
 * matching a real top-flight season's shape without using any real league's name or structure),
 * and the global leaderboard league every team auto-joins.
 */
class FantasyLeagueGenerator
{
    private const CLUB_COUNT = 20;
    private const POSITIONS_PER_CLUB = ['GK' => 3, 'DEF' => 8, 'MID' => 9, 'FWD' => 6]; // 26 total

    public function generate(string $seasonName, \DateTimeInterface $firstKickoff, ?int $cadenceDays = null, ?int $adminId = null): FantasySeason
    {
        $cadenceDays = $cadenceDays ?? (int) (gs('ff_gameweek_cadence_days') ?? 1);

        return DB::transaction(function () use ($seasonName, $firstKickoff, $cadenceDays, $adminId) {
            $season = FantasySeason::create(['name' => $seasonName, 'status' => 'active']);

            $clubs = $this->generateClubs();
            foreach ($clubs as $club) {
                $this->generatePlayersForClub($club);
            }

            $rounds = $this->roundRobinSchedule($clubs->pluck('id')->all());
            $gameweekNumber = 1;
            $kickoff = \Carbon\Carbon::instance($firstKickoff instanceof \Carbon\Carbon ? $firstKickoff : \Carbon\Carbon::parse($firstKickoff));
            $firstGameweekId = null;

            foreach ($rounds as $round) {
                $deadline = $kickoff->copy()->subHour();
                $gameweek = FantasyGameweek::create([
                    'season_id' => $season->id,
                    'number' => $gameweekNumber,
                    'deadline_at' => $deadline,
                    'kickoff_start_at' => $kickoff,
                    'kickoff_end_at' => $kickoff->copy()->addHours(3),
                    'status' => 'upcoming',
                ]);
                $firstGameweekId ??= $gameweek->id;

                $matchOffset = 0;
                foreach ($round as [$homeId, $awayId]) {
                    FantasyFixture::create([
                        'gameweek_id' => $gameweek->id,
                        'home_club_id' => $homeId,
                        'away_club_id' => $awayId,
                        'kickoff_at' => $kickoff->copy()->addMinutes($matchOffset * 5), // stagger kickoffs slightly within the gameweek
                        'status' => 'scheduled',
                    ]);
                    $matchOffset++;
                }

                $gameweekNumber++;
                $kickoff = $kickoff->copy()->addDays($cadenceDays);
            }

            $season->current_gameweek_id = $firstGameweekId;
            $season->save();

            FantasyLeague::create([
                'season_id' => $season->id,
                'name' => 'Global Leaderboard',
                'type' => 'global',
            ]);

            \App\Models\FantasyAuditLog::record('league_generated', $adminId, 'FantasySeason', $season->id, [
                'clubs' => $clubs->count(), 'gameweeks' => $gameweekNumber - 1,
            ]);

            return $season->fresh();
        });
    }

    private function generateClubs()
    {
        $names = FantasyNames::clubNames(self::CLUB_COUNT);
        $clubs = collect();
        foreach ($names as $index => $name) {
            [$primary, $secondary] = FantasyNames::clubColors($index);
            $clubs->push(FantasyClub::create([
                'name' => $name,
                'short_code' => FantasyNames::shortCode($name),
                'color_primary' => $primary,
                'color_secondary' => $secondary,
                'pattern' => FantasyNames::clubPattern($index),
                'strength_rating' => 60 + mt_rand(0, 25), // 60-85, deliberately mt_rand (admin tool, not part of the seeded match simulation)
                'is_active' => true,
            ]));
        }
        return $clubs;
    }

    private function generatePlayersForClub(FantasyClub $club): void
    {
        $used = [];
        foreach (self::POSITIONS_PER_CLUB as $position => $count) {
            for ($i = 0; $i < $count; $i++) {
                $rating = match ($position) {
                    'GK' => 58 + mt_rand(0, 24),
                    'DEF' => 55 + mt_rand(0, 28),
                    'MID' => 55 + mt_rand(0, 32),
                    'FWD' => 55 + mt_rand(0, 32),
                };
                $price = $this->priceFromRating($rating);
                FantasyPlayer::create([
                    'club_id' => $club->id,
                    'name' => FantasyNames::playerName($used),
                    'position' => $position,
                    'rating' => $rating,
                    'price' => $price,
                    'status' => 'fit',
                    'minutes_tendency' => round(0.45 + mt_rand(0, 45) / 100, 3),
                    'is_active' => true,
                ]);
            }
        }
    }

    private function priceFromRating(int $rating): float
    {
        // Roughly 4.0 at a 55 rating up to roughly 12.5 at a 90+ rating, clamped to the admin's configured band.
        $min = (float) (gs('ff_price_min') ?? 3.5);
        $max = (float) (gs('ff_price_max') ?? 15.0);
        $price = 4.0 + max(0, $rating - 55) * 0.19;
        $price = round($price * 2) / 2; // nearest 0.5
        return max($min, min($max, $price));
    }

    /**
     * Standard circle-method double round-robin: for N clubs (N even), N-1 rounds pair every
     * club against every other exactly once; mirroring those rounds with home/away swapped gives
     * a second half-season, so N clubs play 2*(N-1) gameweeks of N/2 fixtures each - for 20 clubs
     * that is 38 gameweeks of 10 fixtures, 380 total, matching the stress-test target exactly.
     *
     * @return array<int, array<int, array{0:int,1:int}>> gameweek index => list of [home_id, away_id]
     */
    private function roundRobinSchedule(array $clubIds): array
    {
        $n = count($clubIds);
        if ($n % 2 !== 0) $clubIds[] = 0; // a bye slot, never reached at exactly 20 clubs
        $n = count($clubIds);
        $fixed = $clubIds[0];
        $rotating = array_slice($clubIds, 1);
        $halfSeason = [];

        for ($round = 0; $round < $n - 1; $round++) {
            $pairings = [];
            $all = array_merge([$fixed], $rotating);
            for ($i = 0; $i < $n / 2; $i++) {
                $a = $all[$i];
                $b = $all[$n - 1 - $i];
                if ($a === 0 || $b === 0) continue; // bye
                // Alternate home advantage across rounds so no club plays all home or all away in the first half.
                $pairings[] = $round % 2 === 0 ? [$a, $b] : [$b, $a];
            }
            $halfSeason[] = $pairings;
            array_unshift($rotating, array_pop($rotating));
        }

        $secondHalf = array_map(fn ($round) => array_map(fn ($pair) => [$pair[1], $pair[0]], $round), $halfSeason);

        return array_merge($halfSeason, $secondHalf);
    }
}
