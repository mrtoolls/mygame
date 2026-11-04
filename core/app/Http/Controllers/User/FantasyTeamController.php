<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyClub;
use App\Models\FantasyPlayer;
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

        $players = FantasyPlayer::active()->with('club')->orderBy('position')->orderByDesc('rating')->get();
        $clubs = FantasyClub::where('is_active', true)->orderBy('name')->get();

        return view('Template::user.fantasy.build-squad', compact('pageTitle', 'season', 'players', 'clubs'));
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
        $available = FantasyPlayer::active()->with('club')->whereNotIn('id', $owned)->orderByDesc('rating')->get();
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
