<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyClub;
use App\Models\FantasyFixture;
use App\Models\FantasyPlayer;
use App\Models\FantasyPlayerFixtureStat;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamPlayer;
use App\Services\Fantasy\FantasyTeamService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FantasyTeamController extends Controller
{
    public function index()
    {
        $pageTitle = 'My Team';
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) {
            return view('Template::user.fantasy.no-season', compact('pageTitle'));
        }

        $team = FantasyTeam::with(['players.player.club'])->where('user_id', auth()->id())->where('season_id', $season->id)->first();
        if (!$team) {
            return redirect()->route('user.fantasy.create')->withNotify([['info', 'Pick your 15-player squad to get started.']]);
        }

        $gameweek = $season->currentGameweek;
        $starting = $team->players->where('squad_role', 'starting')->sortBy(fn ($p) => ['GK' => 0, 'DEF' => 1, 'MID' => 2, 'FWD' => 3][$p->player->position]);
        $bench = $team->players->where('squad_role', 'bench')->sortBy('bench_order');

        return view('Template::user.fantasy.my-team', compact('pageTitle', 'team', 'season', 'gameweek', 'starting', 'bench'));
    }

    public function create()
    {
        $pageTitle = 'Build Your Squad';
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return redirect()->route('user.fantasy.team')->withNotify([['error', 'No active season yet - check back soon.']]);
        if (FantasyTeam::where('user_id', auth()->id())->where('season_id', $season->id)->exists()) {
            return redirect()->route('user.fantasy.team');
        }
        if ($season->currentGameweek && $season->currentGameweek->isDeadlinePassed()) {
            return redirect()->route('user.fantasy.team')->withNotify([['error', 'Squad building is closed for this season - the first gameweek has already locked.']]);
        }

        $players = $this->enrichedPlayers($season, FantasyPlayer::active()->with('club')->orderBy('position')->orderByDesc('rating')->get());
        $clubs = FantasyClub::where('is_active', true)->orderBy('name')->get();

        return view('Template::user.fantasy.build-squad', compact('pageTitle', 'season', 'players', 'clubs'));
    }

    /**
     * Attaches real, computed stats to each player for the builder/transfer filter+sort UI:
     * season-to-date points and recent form (from actual simulated fixtures, empty/null before
     * any have been played - never fabricated), ownership % among this season's existing squads,
     * a simple points-per-credit value score, and each club's next up to 3 fixtures with a
     * difficulty bucket derived from the opponent's admin-set strength_rating.
     */
    private function enrichedPlayers(FantasySeason $season, \Illuminate\Support\Collection $players): \Illuminate\Support\Collection
    {
        $playerIds = $players->pluck('id');

        $statsByPlayer = FantasyPlayerFixtureStat::whereIn('player_id', $playerIds)
            ->join('ff_fixtures', 'ff_fixtures.id', '=', 'ff_player_fixture_stats.fixture_id')
            ->where('ff_fixtures.status', 'final')
            ->orderBy('ff_fixtures.kickoff_at')
            ->get(['ff_player_fixture_stats.player_id as player_id', 'ff_player_fixture_stats.total_points as total_points', 'ff_fixtures.kickoff_at as kickoff_at'])
            ->groupBy('player_id');

        $ownedCounts = FantasyTeamPlayer::whereIn('player_id', $playerIds)
            ->whereHas('team', fn ($q) => $q->where('season_id', $season->id))
            ->selectRaw('player_id, count(*) as c')->groupBy('player_id')->pluck('c', 'player_id');
        $totalTeams = max(1, FantasyTeam::where('season_id', $season->id)->count());

        $nextFixturesByClub = $this->nextFixtureDifficultyByClub($season);

        return $players->map(function ($player) use ($statsByPlayer, $ownedCounts, $totalTeams, $nextFixturesByClub) {
            $stats = $statsByPlayer->get($player->id, collect());
            $points = (int) $stats->sum('total_points');
            $form = $stats->count() ? round((float) $stats->sortByDesc('kickoff_at')->take(4)->avg('total_points'), 1) : null;
            $ownership = round((($ownedCounts[$player->id] ?? 0) / $totalTeams) * 100, 1);
            $basis = $stats->count() ? $points : (float) $player->rating;
            $player->setAttribute('points_total', $points);
            $player->setAttribute('form', $form);
            $player->setAttribute('ownership_pct', $ownership);
            $player->setAttribute('value_score', (float) $player->price > 0 ? round($basis / (float) $player->price, 2) : 0);
            $player->setAttribute('next_fixtures', $nextFixturesByClub[$player->club_id] ?? []);
            return $player;
        });
    }

    private function nextFixtureDifficultyByClub(FantasySeason $season): array
    {
        $upcoming = FantasyFixture::whereHas('gameweek', fn ($q) => $q->where('season_id', $season->id))
            ->where('status', 'scheduled')->orderBy('kickoff_at')->with(['homeClub', 'awayClub'])->limit(200)->get();
        $byClub = [];
        foreach ($upcoming as $fixture) {
            foreach ([[$fixture->home_club_id, $fixture->away_club_id], [$fixture->away_club_id, $fixture->home_club_id]] as [$clubId, $oppId]) {
                if (count($byClub[$clubId] ?? []) >= 3) continue;
                $opponent = (int) $oppId === (int) $fixture->home_club_id ? $fixture->homeClub : $fixture->awayClub;
                $rating = (float) ($opponent->strength_rating ?? 70);
                $byClub[$clubId][] = ['opponent' => $opponent->short_code ?? '?', 'difficulty' => $rating >= 78 ? 'hard' : ($rating >= 68 ? 'medium' : 'easy')];
            }
        }
        return $byClub;
    }

    public function store(Request $request, FantasyTeamService $service)
    {
        $request->validate([
            'name' => 'required|string|max:60',
            'player_ids' => 'required|array|size:15',
            'player_ids.*' => 'integer|exists:ff_players,id',
        ]);

        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return back()->withNotify([['error', 'No active season.']]);

        try {
            $service->createTeam(auth()->user(), $season, $request->name, $request->player_ids);
        } catch (InvalidArgumentException $e) {
            return back()->withNotify([['error', $e->getMessage()]])->withInput();
        }

        return redirect()->route('user.fantasy.team')->withNotify([['success', 'Your squad is set. Good luck!']]);
    }

    public function transfersPage()
    {
        $pageTitle = 'Transfers';
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        $team = $season ? FantasyTeam::with('players.player.club')->where('user_id', auth()->id())->where('season_id', $season->id)->first() : null;
        if (!$team) return redirect()->route('user.fantasy.team');

        $owned = $team->players->pluck('player_id')->all();
        $available = $this->enrichedPlayers($season, FantasyPlayer::active()->with('club')->whereNotIn('id', $owned)->orderByDesc('rating')->get());
        $clubs = FantasyClub::where('is_active', true)->orderBy('name')->get();

        return view('Template::user.fantasy.transfers', compact('pageTitle', 'team', 'available', 'clubs'));
    }

    public function transferStore(Request $request, FantasyTeamService $service)
    {
        $request->validate([
            'out' => 'required|array',
            'in' => 'required|array|same_size:out',
            'out.*' => 'integer', 'in.*' => 'integer',
        ]);

        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'No team found.']]);

        try {
            $result = $service->transfer($team, $request->out, $request->in);
        } catch (InvalidArgumentException $e) {
            return back()->withNotify([['error', $e->getMessage()]]);
        }

        $message = $result['points_cost'] > 0
            ? 'Transfers saved - ' . $result['points_cost'] . ' points will be deducted this gameweek.'
            : 'Transfers saved.';
        return redirect()->route('user.fantasy.team')->withNotify([['success', $message]]);
    }

    /** Builds the service's [player_id => [role, bench_order, captain, vice]] shape from a plain HTML form: a starting[] checkbox list plus single captain_id/vice_id radios - far simpler to post than nested per-player fields. */
    public function lineupStore(Request $request, FantasyTeamService $service)
    {
        $request->validate([
            'starting' => 'required|array', 'starting.*' => 'integer',
            'captain_id' => 'required|integer', 'vice_id' => 'required|integer',
        ]);
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'No team found.']]);

        $allPlayerIds = FantasyTeamPlayer::where('team_id', $team->id)->pluck('player_id');
        $startingIds = array_map('intval', $request->starting);
        $benchOrder = 0;
        $picks = [];
        foreach ($allPlayerIds as $playerId) {
            $isStarting = in_array((int) $playerId, $startingIds, true);
            $picks[$playerId] = [
                'role' => $isStarting ? 'starting' : 'bench',
                'bench_order' => $isStarting ? null : $benchOrder++,
                'captain' => (int) $playerId === (int) $request->captain_id,
                'vice' => (int) $playerId === (int) $request->vice_id,
            ];
        }

        try {
            $service->setLineup($team, $picks);
        } catch (InvalidArgumentException $e) {
            return back()->withNotify([['error', $e->getMessage()]]);
        }

        return redirect()->route('user.fantasy.team')->withNotify([['success', 'Lineup saved.']]);
    }

    public function chipActivate(Request $request, FantasyTeamService $service)
    {
        $request->validate(['chip' => 'required|string']);
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'No team found.']]);

        try {
            $service->activateChip($team, $request->chip);
        } catch (InvalidArgumentException $e) {
            return back()->withNotify([['error', $e->getMessage()]]);
        }

        return redirect()->route('user.fantasy.team')->withNotify([['success', 'Chip activated for this gameweek.']]);
    }

    public function points()
    {
        $pageTitle = 'Points';
        $team = $this->myTeam();
        if (!$team) return redirect()->route('user.fantasy.team');

        $history = \App\Models\FantasyTeamGameweekHistory::with('gameweek')->where('team_id', $team->id)->orderByDesc('gameweek_id')->get();
        $latestGameweekId = $history->first()?->gameweek_id;
        $breakdown = $latestGameweekId
            ? FantasyTeamPlayer::with('player')->where('team_id', $team->id)->get()->map(function ($tp) use ($latestGameweekId) {
                $stat = \App\Models\FantasyPlayerFixtureStat::where('player_id', $tp->player_id)
                    ->whereHas('fixture', fn ($q) => $q->where('gameweek_id', $latestGameweekId))->first();
                return ['player' => $tp->player, 'role' => $tp->squad_role, 'captain' => $tp->is_captain, 'vice' => $tp->is_vice_captain, 'points' => $stat->total_points ?? 0, 'minutes' => $stat->minutes ?? 0];
            })
            : collect();

        return view('Template::user.fantasy.points', compact('pageTitle', 'team', 'history', 'breakdown'));
    }

    private function myTeam(): ?FantasyTeam
    {
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return null;
        return FantasyTeam::where('user_id', auth()->id())->where('season_id', $season->id)->first();
    }
}
