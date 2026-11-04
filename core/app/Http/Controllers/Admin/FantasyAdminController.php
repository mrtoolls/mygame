<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FantasyAuditLog;
use App\Models\FantasyClub;
use App\Models\FantasyContest;
use App\Models\FantasyFixture;
use App\Models\FantasyGameweek;
use App\Models\FantasyPlayer;
use App\Models\FantasyPlayerFixtureStat;
use App\Models\FantasyScoringRule;
use App\Models\FantasySeason;
use App\Models\FantasyStatCorrection;
use App\Services\Fantasy\FantasyContestService;
use App\Services\Fantasy\FantasyLeagueGenerator;
use App\Services\Fantasy\FantasyScoring;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FantasyAdminController extends Controller
{
    public function index()
    {
        $pageTitle = 'Fantasy Football';
        $season = FantasySeason::latest('id')->first();
        $stats = [
            'seasons' => FantasySeason::count(), 'clubs' => FantasyClub::count(), 'players' => FantasyPlayer::count(),
            'fixtures' => FantasyFixture::count(), 'teams' => \App\Models\FantasyTeam::count(),
            'open_contests' => FantasyContest::whereIn('status', ['open', 'locked'])->count(),
        ];
        return view('admin.fantasy.index', compact('pageTitle', 'season', 'stats'));
    }

    public function generate(Request $request, FantasyLeagueGenerator $generator)
    {
        $request->validate(['name' => 'required|string|max:60', 'first_kickoff' => 'required|date', 'cadence_days' => 'nullable|integer|min:1|max:7']);
        try {
            $season = $generator->generate($request->name, new \DateTime($request->first_kickoff), $request->cadence_days, auth('admin')->id());
        } catch (\Throwable $e) {
            return back()->withNotify([['error', 'League generation failed: ' . $e->getMessage()]]);
        }
        return redirect()->route('admin.fantasy.index')->withNotify([['success', 'Generated "' . $season->name . '" - 20 clubs, ' . FantasyPlayer::count() . ' players, 380 fixtures.']]);
    }

    public function clubs()
    {
        $pageTitle = 'Clubs';
        $clubs = FantasyClub::withCount('players')->orderBy('name')->paginate(30);
        return view('admin.fantasy.clubs', compact('pageTitle', 'clubs'));
    }

    public function players(Request $request)
    {
        $pageTitle = 'Players';
        $players = FantasyPlayer::with('club')
            ->when($request->club_id, fn ($q) => $q->where('club_id', $request->club_id))
            ->when($request->position, fn ($q) => $q->where('position', $request->position))
            ->orderByDesc('rating')->paginate(40);
        $clubs = FantasyClub::orderBy('name')->get(['id', 'name']);
        return view('admin.fantasy.players', compact('pageTitle', 'players', 'clubs'));
    }

    public function playerUpdate(Request $request, FantasyPlayer $player)
    {
        $request->validate([
            'price' => 'required|numeric|min:0', 'rating' => 'required|numeric|min:0|max:99',
            'status' => 'required|in:fit,injured,suspended',
        ]);
        $old = $player->only(['price', 'rating', 'status']);
        $player->update($request->only(['price', 'rating', 'status']));
        FantasyAuditLog::record('player_updated', auth('admin')->id(), 'FantasyPlayer', $player->id, ['old' => $old, 'new' => $request->only(['price', 'rating', 'status'])]);
        return back()->withNotify([['success', 'Player updated.']]);
    }

    public function scoring()
    {
        $pageTitle = 'Scoring Rules';
        $rules = FantasyScoringRule::orderBy('key')->get();
        return view('admin.fantasy.scoring', compact('pageTitle', 'rules'));
    }

    public function scoringUpdate(Request $request)
    {
        $request->validate(['values' => 'required|array']);
        foreach ($request->values as $key => $value) {
            FantasyScoringRule::where('key', $key)->update(['value' => (float) $value]);
        }
        FantasyAuditLog::record('scoring_updated', auth('admin')->id(), null, null, $request->values);
        return back()->withNotify([['success', 'Scoring rules saved.']]);
    }

    public function settings()
    {
        $pageTitle = 'Fantasy Settings';
        return view('admin.fantasy.settings', compact('pageTitle'));
    }

    public function settingsUpdate(Request $request)
    {
        $request->validate([
            'ff_enabled' => 'nullable|boolean', 'ff_match_duration_minutes' => 'required|integer|min:1|max:60',
            'ff_gameweek_cadence_days' => 'required|integer|min:1|max:7', 'ff_contest_fee_percent' => 'required|numeric|min:0|max:100',
            'ff_settlement_review_minutes' => 'required|integer|min:0|max:1440',
            'ff_price_change_enabled' => 'nullable|boolean', 'ff_price_change_step' => 'required|numeric|min:0.1|max:5',
            'ff_price_min' => 'required|numeric|min:0.1', 'ff_price_max' => 'required|numeric|min:0.1',
            'ff_max_entry_stake' => 'required|numeric|min:0', 'ff_daily_stake_cap' => 'required|numeric|min:0',
        ]);

        $general = gs();
        $general->ff_enabled = $request->boolean('ff_enabled');
        $general->ff_match_duration_minutes = $request->ff_match_duration_minutes;
        $general->ff_gameweek_cadence_days = $request->ff_gameweek_cadence_days;
        $general->ff_contest_fee_percent = $request->ff_contest_fee_percent;
        $general->ff_settlement_review_minutes = $request->ff_settlement_review_minutes;
        $general->ff_price_change_enabled = $request->boolean('ff_price_change_enabled');
        $general->ff_price_change_step = $request->ff_price_change_step;
        $general->ff_price_min = $request->ff_price_min;
        $general->ff_price_max = $request->ff_price_max;
        $general->ff_max_entry_stake = $request->ff_max_entry_stake;
        $general->ff_daily_stake_cap = $request->ff_daily_stake_cap;
        $general->save();
        \Illuminate\Support\Facades\Cache::forget('GeneralSetting');

        FantasyAuditLog::record('settings_updated', auth('admin')->id(), null, null, $request->except('_token'));
        return back()->withNotify([['success', 'Settings saved.']]);
    }

    public function killSwitch(Request $request)
    {
        $general = gs();
        $general->ff_enabled = !$general->ff_enabled;
        $general->save();
        \Illuminate\Support\Facades\Cache::forget('GeneralSetting');
        FantasyAuditLog::record($general->ff_enabled ? 'kill_switch_enabled' : 'kill_switch_disabled', auth('admin')->id());
        return back()->withNotify([['success', 'Fantasy Football is now ' . ($general->ff_enabled ? 'enabled' : 'disabled') . '.']]);
    }

    public function contests()
    {
        $pageTitle = 'Contests';
        $contests = FantasyContest::with('gameweek')->orderByDesc('id')->paginate(30);
        $openGameweeks = FantasyGameweek::whereIn('status', ['upcoming', 'active'])->orderBy('number')->get();
        return view('admin.fantasy.contests', compact('pageTitle', 'contests', 'openGameweeks'));
    }

    public function contestStore(Request $request)
    {
        $request->validate([
            'type' => 'required|in:h2h,gameweek,sponsored', 'gameweek_id' => 'required|exists:ff_gameweeks,id',
            'name' => 'required|string|max:100', 'entry_fee' => 'required_unless:type,sponsored|numeric|min:0',
            'sponsored_pool' => 'required_if:type,sponsored|nullable|numeric|min:0',
            'min_entrants' => 'required|integer|min:2', 'max_entrants' => 'nullable|integer|min:2',
        ]);
        $gameweek = FantasyGameweek::findOrFail($request->gameweek_id);

        $tiers = $request->type === 'h2h'
            ? [['top_percent' => 50, 'share_percent' => 100]]
            : ($request->tiers ?: [['top_percent' => 100, 'share_percent' => 100]]);

        $contest = FantasyContest::create([
            'type' => $request->type, 'gameweek_id' => $gameweek->id, 'name' => $request->name,
            'entry_fee' => $request->type === 'sponsored' ? 0 : $request->entry_fee,
            'sponsored_pool' => $request->type === 'sponsored' ? $request->sponsored_pool : null,
            'min_entrants' => $request->min_entrants, 'max_entrants' => $request->type === 'h2h' ? 2 : $request->max_entrants,
            'status' => 'open', 'prize_structure' => $tiers, 'deadline_at' => $gameweek->deadline_at,
            'created_by_admin_id' => auth('admin')->id(),
        ]);
        FantasyAuditLog::record('contest_created', auth('admin')->id(), 'FantasyContest', $contest->id);
        return back()->withNotify([['success', 'Contest created.']]);
    }

    public function statCorrections(Request $request)
    {
        $pageTitle = 'Stat Corrections';
        $fixture = $request->fixture_id ? FantasyFixture::with(['homeClub', 'awayClub'])->find($request->fixture_id) : null;
        $stats = $fixture ? FantasyPlayerFixtureStat::with('player')->where('fixture_id', $fixture->id)->get() : collect();
        $recentFixtures = FantasyFixture::with(['homeClub', 'awayClub'])->where('status', 'final')->latest('id')->limit(30)->get();
        $log = FantasyStatCorrection::latest('id')->limit(50)->get();
        return view('admin.fantasy.stat-corrections', compact('pageTitle', 'fixture', 'stats', 'recentFixtures', 'log'));
    }

    /** Re-scores a single stat field before payout only - FantasyContestService refuses once a contest has moved past 'locked', so this cannot touch an already-settled payout. */
    public function statCorrectionStore(Request $request, FantasyScoring $scoring)
    {
        $request->validate([
            'fixture_id' => 'required|exists:ff_fixtures,id', 'player_id' => 'required|exists:ff_players,id',
            'field' => 'required|in:minutes,goals,assists,yellow_cards,red_cards,own_goals,penalties_saved,penalties_missed,saves,goals_conceded,bonus',
            'value' => 'required|integer|min:0', 'reason' => 'nullable|string|max:255',
        ]);

        $stat = FantasyPlayerFixtureStat::where('fixture_id', $request->fixture_id)->where('player_id', $request->player_id)->firstOrFail();
        $gameweekId = $stat->fixture->gameweek_id;
        $anySettled = FantasyContest::where('gameweek_id', $gameweekId)->where('status', 'settled')->exists();
        if ($anySettled) {
            return back()->withNotify([['error', 'This gameweek\'s contests have already paid out - stats are locked.']]);
        }

        $old = $stat->{$request->field};
        $stat->{$request->field} = $request->value;
        $stat->save();
        $scoring->recalculateAndSave($stat);

        FantasyStatCorrection::create([
            'fixture_id' => $request->fixture_id, 'player_id' => $request->player_id,
            'field' => $request->field, 'old_value' => (string) $old, 'new_value' => (string) $request->value,
            'admin_id' => auth('admin')->id(), 'reason' => $request->reason, 'created_at' => now(),
        ]);

        return back()->withNotify([['success', 'Stat corrected and points recalculated.']]);
    }

    public function auditLog()
    {
        $pageTitle = 'Audit Log';
        $entries = FantasyAuditLog::orderByDesc('id')->paginate(50);
        return view('admin.fantasy.audit-log', compact('pageTitle', 'entries'));
    }
}
