<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyLeague;
use App\Models\FantasyLeagueMember;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FantasyLeagueController extends Controller
{
    public function index()
    {
        $pageTitle = 'Leagues';
        $team = $this->myTeam();
        if (!$team) return redirect()->route('user.fantasy.team');

        $memberships = FantasyLeagueMember::with('league')->where('team_id', $team->id)->get();
        $global = FantasyLeague::where('season_id', $team->season_id)->where('type', 'global')->first();
        $globalStandings = $global ? FantasyTeam::where('season_id', $team->season_id)->orderByDesc('total_points')->orderBy('id')->limit(50)->get(['id', 'name', 'user_id', 'total_points', 'overall_rank']) : collect();

        return view('Template::user.fantasy.leagues', compact('pageTitle', 'team', 'memberships', 'global', 'globalStandings'));
    }

    public function show(FantasyLeague $league)
    {
        $pageTitle = $league->name;
        $members = FantasyLeagueMember::with('team')->where('league_id', $league->id)->get()
            ->map(fn ($m) => $m->team)->filter()->sortByDesc('total_points')->values();

        $h2hFixtures = collect();
        if ($league->type === 'h2h') {
            $h2hFixtures = \App\Models\FantasyH2hFixture::with(['teamA', 'teamB', 'gameweek'])->where('league_id', $league->id)->orderByDesc('gameweek_id')->get();
        }

        return view('Template::user.fantasy.league-show', compact('pageTitle', 'league', 'members', 'h2hFixtures'));
    }

    public function create(Request $request)
    {
        $request->validate(['name' => 'required|string|max:60', 'type' => 'required|in:classic,h2h']);
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'Build your squad first.']]);

        $league = FantasyLeague::create([
            'season_id' => $team->season_id, 'name' => $request->name, 'type' => $request->type,
            'join_code' => strtoupper(Str::random(6)), 'creator_team_id' => $team->id,
        ]);
        FantasyLeagueMember::create(['league_id' => $league->id, 'team_id' => $team->id, 'joined_at' => now()]);

        return redirect()->route('user.fantasy.leagues.show', $league->id)->withNotify([['success', 'League created - share the code ' . $league->join_code . ' to invite managers.']]);
    }

    public function join(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'Build your squad first.']]);

        $league = FantasyLeague::where('join_code', strtoupper($request->code))->first();
        if (!$league) return back()->withNotify([['error', 'No league found with that code.']]);
        if ((int) $league->season_id !== (int) $team->season_id) return back()->withNotify([['error', 'That league is from a different season.']]);
        if (FantasyLeagueMember::where('league_id', $league->id)->where('team_id', $team->id)->exists()) {
            return redirect()->route('user.fantasy.leagues.show', $league->id);
        }
        if ($league->max_members && FantasyLeagueMember::where('league_id', $league->id)->count() >= $league->max_members) {
            return back()->withNotify([['error', 'That league is full.']]);
        }

        FantasyLeagueMember::create(['league_id' => $league->id, 'team_id' => $team->id, 'joined_at' => now()]);
        return redirect()->route('user.fantasy.leagues.show', $league->id)->withNotify([['success', 'Joined ' . $league->name . '.']]);
    }

    private function myTeam(): ?FantasyTeam
    {
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return null;
        return FantasyTeam::where('user_id', auth()->id())->where('season_id', $season->id)->first();
    }
}
