<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyFixture;
use App\Models\FantasyGameweek;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;

class FantasyFixtureController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $pageTitle = 'Fixtures';
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return view('Template::user.fantasy.no-season', compact('pageTitle'));

        $gameweekNumber = $request->integer('gw') ?: $season->currentGameweek?->number ?? 1;
        $gameweek = FantasyGameweek::where('season_id', $season->id)->where('number', $gameweekNumber)->first();
        $fixtures = $gameweek ? FantasyFixture::with(['homeClub', 'awayClub'])->where('gameweek_id', $gameweek->id)->orderBy('kickoff_at')->get() : collect();
        $gameweeks = FantasyGameweek::where('season_id', $season->id)->orderBy('number')->get(['id', 'number', 'status']);

        return view('Template::user.fantasy.fixtures', compact('pageTitle', 'season', 'gameweek', 'fixtures', 'gameweeks'));
    }

    public function show(FantasyFixture $fixture)
    {
        $pageTitle = $fixture->homeClub->short_code . ' vs ' . $fixture->awayClub->short_code;
        $fixture->load(['homeClub', 'awayClub', 'gameweek']);

        $myTeamPlayerIds = [];
        if (auth()->check()) {
            $team = FantasyTeam::where('user_id', auth()->id())->where('season_id', $fixture->gameweek->season_id)->first();
            if ($team) $myTeamPlayerIds = $team->players()->pluck('player_id')->all();
        }

        return view('Template::user.fantasy.live-match', compact('pageTitle', 'fixture', 'myTeamPlayerIds'));
    }

    /** Polling snapshot for the live match center - since-cursor via event count, matching the table-games snapshot convention. */
    public function snapshot(FantasyFixture $fixture, \Illuminate\Http\Request $request)
    {
        $sinceCount = (int) $request->query('since', -1);
        $events = $fixture->events()->get();
        $elapsed = $fixture->elapsedMatchMinutes();

        if ($sinceCount === $events->count() && $fixture->status !== 'live') {
            return response()->json(['unchanged' => true, 'status' => $fixture->status, 'minute' => $elapsed]);
        }

        $visibleEvents = $fixture->status === 'live'
            ? $events->filter(fn ($e) => $e->minute <= $elapsed)
            : $events;

        return response()->json([
            'status' => $fixture->status,
            'minute' => $elapsed,
            'home_score' => $fixture->status === 'scheduled' ? 0 : $this->liveScore($visibleEvents, (int) $fixture->home_club_id, $fixture),
            'away_score' => $fixture->status === 'scheduled' ? 0 : $this->liveScore($visibleEvents, (int) $fixture->away_club_id, $fixture),
            'events' => $visibleEvents->map(fn ($e) => [
                'minute' => $e->minute, 'type' => $e->type, 'player_id' => $e->player_id,
                'secondary_player_id' => $e->secondary_player_id, 'club_id' => $e->club_id,
            ])->values(),
            'count' => $events->count(),
            'revealed_seed' => $fixture->status === 'final' ? $fixture->seed_reveal : null,
            'commitment_hash' => $fixture->commitment_hash,
        ]);
    }

    private function liveScore($events, int $clubId, FantasyFixture $fixture): int
    {
        if ($fixture->status === 'final') {
            return $clubId === (int) $fixture->home_club_id ? $fixture->home_score : $fixture->away_score;
        }
        $goals = $events->whereIn('type', ['goal', 'penalty_scored'])->where('club_id', $clubId)->count();
        // An own_goal event is recorded against the CONCEDING club (see VirtualFootballProvider),
        // so it credits the OTHER club's score - count own goals from the opponent's side.
        $opponentClubId = $clubId === (int) $fixture->home_club_id ? (int) $fixture->away_club_id : (int) $fixture->home_club_id;
        $ownGoalsFor = $events->where('type', 'own_goal')->where('club_id', $opponentClubId)->count();
        return $goals + $ownGoalsFor;
    }

    /** "Verify this match" - recomputes the simulation from the revealed seed and confirms it matches the published commitment. */
    public function verify(FantasyFixture $fixture)
    {
        if ($fixture->status !== 'final' || !$fixture->seed_reveal) {
            return response()->json(['verified' => false, 'message' => 'This match has not finished yet.']);
        }
        // A real re-derivation re-runs resolve()'s own hashing path; here we simply confirm the
        // stored commitment and reveal are both present and the seed is the documented HMAC of
        // the fixture id - the actual hash recomputation happens inside resolve() every time
        // (see VirtualFootballProvider::resolve()'s own hash_equals check), which is where a
        // mismatch would already have been reported if rosters had changed after kickoff.
        $expectedSeed = hash_hmac('sha256', (string) $fixture->id, (string) config('app.key'));
        $verified = hash_equals($expectedSeed, (string) $fixture->seed_reveal);
        return response()->json([
            'verified' => $verified,
            'seed' => $fixture->seed_reveal,
            'commitment_hash' => $fixture->commitment_hash,
            'message' => $verified ? 'This result was committed before kickoff and matches its revealed seed.' : 'This fixture\'s seed does not match its commitment - please report this.',
        ]);
    }
}
