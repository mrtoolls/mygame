<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionFlag;
use App\Models\User;
use App\Services\CompetitionService;
use App\Support\CompetitionRichText;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    public function index()
    {
        $pageTitle = 'Competitions';
        $live = Competition::with('prizes')->where('status', Competition::STATUS_LIVE)->orderBy('end_at')->get();
        $upcoming = Competition::with('prizes')->where('status', Competition::STATUS_SCHEDULED)->orderBy('start_at')->get();
        $past = Competition::with('prizes')->whereIn('status', [Competition::STATUS_ENDED, Competition::STATUS_FINALIZED, Competition::STATUS_PAID])
            ->orderByDesc('end_at')->limit(12)->get();

        return view('Template::user.competitions.index', compact('pageTitle', 'live', 'upcoming', 'past'));
    }

    public function show(string $slug, CompetitionService $service)
    {
        $competition = Competition::where('slug', $slug)->firstOrFail();
        $pageTitle = $competition->title;
        $safeDescription = CompetitionRichText::sanitize($competition->description);
        $safeRules = CompetitionRichText::sanitize($competition->rules);

        if (in_array($competition->status, [Competition::STATUS_FINALIZED, Competition::STATUS_PAID], true)) {
            $standings = $competition->results()->with('user:id,username')->orderBy('rank')->limit(50)->get()
                ->map(fn ($r) => (object) ['user_id' => $r->user_id, 'username' => $r->user->username ?? 'Deleted user', 'metric_value' => $r->metric_value, 'rank' => $r->rank, 'disqualified' => (bool) $r->disqualified_at]);
            $metricLabel = $competition->results()->first()?->metric_label;
        } else {
            $preview = $service->previewRanking($competition);
            $userIds = $preview['rows']->pluck('user_id');
            $usernames = User::whereIn('id', $userIds)->pluck('username', 'id');
            $standings = $preview['rows']->take(50)->values()->map(fn ($row, $i) => (object) [
                'user_id' => $row->user_id,
                'username' => $usernames[$row->user_id] ?? 'Player',
                'metric_value' => $row->metric_value,
                'rank' => $i + 1,
                'disqualified' => false,
            ]);
            $metricLabel = $preview['metric_label'];
        }

        $prizes = $competition->prizes()->orderBy('rank')->get();
        $myResult = auth()->check() ? $competition->results()->where('user_id', auth()->id())->first() : null;

        return view('Template::user.competitions.show', compact('pageTitle', 'competition', 'standings', 'metricLabel', 'prizes', 'myResult', 'safeDescription', 'safeRules'));
    }

    public function flag(Request $request, string $slug)
    {
        $competition = Competition::where('slug', $slug)->firstOrFail();

        $request->validate([
            'related_username' => 'required|string|exists:users,username',
            'reason' => 'required|string|max:500',
        ]);

        $relatedUser = User::where('username', $request->related_username)->first();
        if ($relatedUser && $relatedUser->id === auth()->id()) {
            $notify[] = ['error', 'You cannot flag yourself'];
            return back()->withNotify($notify);
        }

        CompetitionFlag::create([
            'competition_id' => $competition->id,
            'user_id' => auth()->id(),
            'related_user_id' => $relatedUser?->id,
            'reason' => $request->reason,
        ]);

        $notify[] = ['success', 'Thank you - our team will review this before any prizes are paid out'];
        return back()->withNotify($notify);
    }
}
