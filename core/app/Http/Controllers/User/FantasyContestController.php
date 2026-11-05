<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyContest;
use App\Models\FantasyContestEntry;
use App\Models\FantasyContestSquad;
use App\Models\FantasyPlayer;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Services\Fantasy\FantasyContestService;
use App\Services\Fantasy\FantasyContestSquadService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FantasyContestController extends Controller
{
    public function index()
    {
        $pageTitle = 'Contests';
        $team = $this->myTeam();
        if (!$team) return redirect()->route('user.fantasy.team');

        $gameweek = $team->season->currentGameweek;
        $contests = $gameweek ? FantasyContest::where('gameweek_id', $gameweek->id)->whereIn('status', ['open', 'locked'])->get() : collect();
        $myEntries = FantasyContestEntry::where('user_id', auth()->id())->pluck('contest_id', 'contest_id');
        $mySquads = FantasyContestSquad::with('players.player')->where('user_id', auth()->id())->whereIn('contest_id', $contests->pluck('id'))->get()->keyBy('contest_id');
        $entryCounts = FantasyContestEntry::whereIn('contest_id', $contests->pluck('id'))->selectRaw('contest_id, COUNT(*) as c')->groupBy('contest_id')->pluck('c', 'contest_id');

        $myRecentEntries = FantasyContestEntry::with('contest')->where('user_id', auth()->id())->latest('id')->limit(20)->get();

        return view('Template::user.fantasy.contests', compact('pageTitle', 'team', 'gameweek', 'contests', 'myEntries', 'mySquads', 'entryCounts', 'myRecentEntries'));
    }

    public function squad(FantasyContest $contest)
    {
        $team = $this->myTeam();
        if (!$team) return redirect()->route('user.fantasy.team');
        abort_unless($contest->type === 'quick' && $contest->status === 'open', 404);
        abort_unless((int) $contest->gameweek->season_id === (int) $team->season_id, 404);
        $squad = FantasyContestSquad::with('players.player')->where('contest_id', $contest->id)->where('user_id', auth()->id())->first();
        abort_if(FantasyContestEntry::where('contest_id', $contest->id)->where('user_id', auth()->id())->exists(), 403);
        $savedPlayerIds = $squad?->players->pluck('player_id')->all() ?? [];
        $players = FantasyPlayer::with('club')->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $savedPlayerIds))->orderBy('position')->orderByDesc('rating')->get();
        return view('Template::user.fantasy.contest-squad', [
            'pageTitle' => 'Build Contest Squad', 'team' => $team, 'contest' => $contest,
            'squad' => $squad, 'players' => $players,
        ]);
    }

    public function saveSquad(Request $request, FantasyContest $contest, FantasyContestSquadService $service)
    {
        $team = $this->myTeam();
        if (!$team) return redirect()->route('user.fantasy.team');
        abort_unless($contest->type === 'quick' && (int) $contest->gameweek->season_id === (int) $team->season_id, 404);
        $request->validate([
            'name' => 'required|string|max:60', 'player_ids' => 'required|array|size:15',
            'player_ids.*' => 'required|integer|distinct|exists:ff_players,id',
        ]);
        try {
            $service->createOrReplace(auth()->user(), $contest, $request->name, $request->player_ids);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['player_ids' => $e->getMessage()])->withInput();
        }
        return redirect()->route('user.fantasy.contests.index')->withNotify([['success', 'Your contest squad is saved. You can now enter the contest.']]);
    }

    public function enter(Request $request, FantasyContest $contest, FantasyContestService $service)
    {
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'Build your squad first.']]);

        try {
            $contestSquad = $contest->type === 'quick'
                ? FantasyContestSquad::where('contest_id', $contest->id)->where('user_id', auth()->id())->first()
                : null;
            $service->enter(auth()->user(), $contest, $team, $contestSquad);
        } catch (InvalidArgumentException $e) {
            return back()->withNotify([['error', $e->getMessage()]]);
        }

        return back()->withNotify([['success', 'You are entered into ' . $contest->name . '.']]);
    }

    private function myTeam(): ?FantasyTeam
    {
        $season = FantasySeason::where('status', 'active')->latest('id')->first();
        if (!$season) return null;
        return FantasyTeam::where('user_id', auth()->id())->where('season_id', $season->id)->first();
    }
}
