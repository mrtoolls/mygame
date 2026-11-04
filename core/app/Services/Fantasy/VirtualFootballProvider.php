<?php

namespace App\Services\Fantasy;

use App\Models\FantasyFixture;
use App\Models\FantasyFixtureEvent;
use App\Models\FantasyPlayer;
use App\Models\FantasyPlayerFixtureStat;
use App\Support\DeterministicRandom;
use Illuminate\Support\Facades\DB;

/**
 * The only FootballDataProvider implementation today. The seed for a fixture is
 * HMAC-SHA256(APP_KEY, fixture id) - APP_KEY is already a secret this app keeps in .env (which
 * this feature must not touch), so reusing it here needs no new secret, no install.php/.env
 * change, and nobody without server access can predict a result before it is revealed. Because
 * MatchSimulator is a pure, deterministic function of (seed, rosters), the "sealed result" never
 * actually needs to be stored anywhere between commit() and resolve() - recomputing it from the
 * same seed and the same roster rows reproduces it exactly, which is also exactly what "Verify
 * this match" does client-side after the seed is revealed.
 */
class VirtualFootballProvider implements FootballDataProvider
{
    public function __construct(private readonly MatchSimulator $simulator) {}

    public function commit(FantasyFixture $fixture): string
    {
        if ($fixture->commitment_hash) return $fixture->commitment_hash;
        $seed = $this->seedFor($fixture);
        $result = $this->runSimulation($fixture, $seed);
        $hash = $this->hashResult($result);
        $fixture->commitment_hash = $hash;
        $fixture->save();
        return $hash;
    }

    public function resolve(FantasyFixture $fixture): void
    {
        if ($fixture->status === 'final') return;

        $seed = $this->seedFor($fixture);
        $result = $this->runSimulation($fixture, $seed);
        $hash = $this->hashResult($result);
        if ($fixture->commitment_hash && !hash_equals($fixture->commitment_hash, $hash)) {
            // Rosters (fitness/active flags) changed between kickoff and full time - the
            // commitment published at kickoff no longer matches what's about to be persisted, so
            // "Verify this match" will fail for this fixture. Not fatal (the result below is
            // still this fixture's authoritative outcome), but worth a report so an admin can see
            // it happened instead of silently losing verifiability.
            report(new \RuntimeException("Fantasy fixture {$fixture->id}: commitment hash mismatch at resolve time - a player's status likely changed after kickoff."));
        }

        DB::transaction(function () use ($fixture, $result, $seed) {
            $fixture->home_score = $result['home_score'];
            $fixture->away_score = $result['away_score'];
            $fixture->status = 'final';
            $fixture->seed_reveal = $seed;
            $fixture->simulated_at = now();
            $fixture->save();

            $playerClubIds = FantasyPlayer::whereIn('id', array_column($result['player_stats'], 'id'))->pluck('club_id', 'id');

            foreach ($result['events'] as $event) {
                FantasyFixtureEvent::create([
                    'fixture_id' => $fixture->id,
                    'minute' => $event['minute'],
                    'type' => $event['type'],
                    'player_id' => $event['player_id'],
                    'secondary_player_id' => $event['secondary_player_id'],
                    'club_id' => $event['side'] === 'home' ? $fixture->home_club_id : $fixture->away_club_id,
                ]);
            }

            foreach ($result['player_stats'] as $stat) {
                FantasyPlayerFixtureStat::updateOrCreate(
                    ['fixture_id' => $fixture->id, 'player_id' => $stat['id']],
                    [
                        'club_id' => $playerClubIds[$stat['id']] ?? null,
                        'minutes' => $stat['minutes'], 'goals' => $stat['goals'], 'assists' => $stat['assists'],
                        'yellow_cards' => $stat['yellow_cards'], 'red_cards' => $stat['red_cards'],
                        'own_goals' => $stat['own_goals'], 'penalties_saved' => $stat['penalties_saved'],
                        'penalties_missed' => $stat['penalties_missed'], 'saves' => $stat['saves'],
                        'goals_conceded' => $stat['goals_conceded'], 'clean_sheet' => $stat['clean_sheet'],
                        'bonus' => $stat['bonus'], 'started' => $stat['started'],
                    ]
                );

                if (!empty($stat['injured'])) {
                    FantasyPlayer::whereKey($stat['id'])->update(['status' => 'injured', 'status_until' => now()->addDays(rand(3, 14))]);
                }
            }

            app(FantasyScoring::class);
            $this->recalculatePoints($fixture);
        });
    }

    public function reveal(FantasyFixture $fixture): ?string
    {
        return $fixture->seed_reveal;
    }

    private function recalculatePoints(FantasyFixture $fixture): void
    {
        $scoring = app(FantasyScoring::class);
        $rules = $scoring->rules();
        FantasyPlayerFixtureStat::with('player')->where('fixture_id', $fixture->id)->get()->each(function ($stat) use ($scoring, $rules) {
            $position = $stat->player->position ?? 'MID';
            $stat->total_points = $scoring->calculate($position, [
                'minutes' => $stat->minutes, 'goals' => $stat->goals, 'assists' => $stat->assists,
                'yellow_cards' => $stat->yellow_cards, 'red_cards' => $stat->red_cards,
                'own_goals' => $stat->own_goals, 'penalties_saved' => $stat->penalties_saved,
                'penalties_missed' => $stat->penalties_missed, 'saves' => $stat->saves,
                'goals_conceded' => $stat->goals_conceded, 'clean_sheet' => $stat->clean_sheet,
                'bonus' => $stat->bonus,
            ], $rules);
            $stat->save();
        });
    }

    private function seedFor(FantasyFixture $fixture): string
    {
        return hash_hmac('sha256', (string) $fixture->id, (string) config('app.key'));
    }

    private function runSimulation(FantasyFixture $fixture, string $seed): array
    {
        $rng = new DeterministicRandom($seed);
        $home = $fixture->homeClub;
        $away = $fixture->awayClub;
        $homeRoster = $home->players()->active()->orderBy('id')->get();
        $awayRoster = $away->players()->active()->orderBy('id')->get();

        return $this->simulator->simulate(
            $rng,
            ['strength_rating' => (float) $home->strength_rating],
            $homeRoster,
            ['strength_rating' => (float) $away->strength_rating],
            $awayRoster
        );
    }

    private function hashResult(array $result): string
    {
        return hash('sha256', json_encode([$result['home_score'], $result['away_score'], $result['events'], $result['player_stats']]));
    }
}
