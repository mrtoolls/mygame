<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\FantasyContest;
use App\Models\FantasyContestEntry;
use App\Models\FantasySeason;
use App\Models\FantasyTeam;
use App\Services\Fantasy\FantasyContestService;
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
        $entryCounts = FantasyContestEntry::whereIn('contest_id', $contests->pluck('id'))->selectRaw('contest_id, COUNT(*) as c')->groupBy('contest_id')->pluck('c', 'contest_id');

        $myRecentEntries = FantasyContestEntry::with('contest')->where('user_id', auth()->id())->latest('id')->limit(20)->get();

        return view('Template::user.fantasy.contests', compact('pageTitle', 'team', 'gameweek', 'contests', 'myEntries', 'entryCounts', 'myRecentEntries'));
    }

    public function enter(Request $request, FantasyContest $contest, FantasyContestService $service)
    {
        $team = $this->myTeam();
        if (!$team) return back()->withNotify([['error', 'Build your squad first.']]);

        try {
            $service->enter(auth()->user(), $contest, $team);
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
