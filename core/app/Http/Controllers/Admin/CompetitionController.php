<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionFlag;
use App\Models\CompetitionPrize;
use App\Models\Currency;
use App\Services\CompetitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompetitionController extends Controller
{
    public function index()
    {
        $pageTitle = 'Competitions';
        $competitions = Competition::orderByDesc('id')->paginate(getPaginate());
        return view('admin.competitions.index', compact('pageTitle', 'competitions'));
    }

    public function create()
    {
        $pageTitle = 'Add Competition';
        $competition = new Competition();
        return view('admin.competitions.create', compact('pageTitle', 'competition'));
    }

    public function store(Request $request)
    {
        $this->validateCommon($request, true);

        $competition = new Competition();
        $this->fillCommon($competition, $request, true);
        $competition->created_by = auth('admin')->id();
        $competition->save();

        $this->syncPrizes($competition, $request);
        $this->logAudit($competition, 'created', ['type' => $competition->type]);

        $notify[] = ['success', 'Competition created successfully'];
        return to_route('admin.competitions.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $pageTitle = 'Edit Competition';
        $competition = Competition::findOrFail($id);
        return view('admin.competitions.edit', compact('pageTitle', 'competition'));
    }

    public function update(Request $request, $id)
    {
        $competition = Competition::findOrFail($id);
        $this->validateCommon($request, false, $competition);
        $this->fillCommon($competition, $request, false);
        $competition->save();

        $this->syncPrizes($competition, $request, $competition->isScoringLocked());
        $this->logAudit($competition, 'updated', $request->only(['status']));

        $notify[] = ['success', 'Competition updated successfully'];
        return to_route('admin.competitions.index')->withNotify($notify);
    }

    public function destroy($id)
    {
        $competition = Competition::findOrFail($id);
        if (!in_array($competition->status, [Competition::STATUS_DRAFT, Competition::STATUS_SCHEDULED], true)) {
            $notify[] = ['error', 'Only a draft or scheduled competition can be deleted'];
            return back()->withNotify($notify);
        }
        if ($competition->banner_image) {
            @unlink('assets/images/competitions/' . $competition->banner_image);
        }
        $competition->delete();

        $notify[] = ['success', 'Competition deleted successfully'];
        return back()->withNotify($notify);
    }

    public function results($id)
    {
        $pageTitle = 'Competition Results';
        $competition = Competition::findOrFail($id);
        $results = $competition->results()->with('user:id,username,email')->orderBy('rank')->paginate(getPaginate());
        $currencies = Currency::where('enabled', true)->orderBy('code')->get();
        return view('admin.competitions.results', compact('pageTitle', 'competition', 'results', 'currencies'));
    }

    public function finalizeNow($id, CompetitionService $service)
    {
        $competition = Competition::findOrFail($id);
        $competition->end_at = min($competition->end_at, now());
        $competition->save();
        $ran = $service->finalize($competition);

        $notify[] = [$ran ? 'success' : 'error', $ran ? 'Competition finalized' : 'Not finalizable yet - the grace period has not elapsed'];
        return back()->withNotify($notify);
    }

    public function payResult(Request $request, $resultId)
    {
        $result = \App\Models\CompetitionResult::findOrFail($resultId);
        if ($result->isPaid()) {
            $notify[] = ['error', 'This prize has already been marked as paid'];
            return back()->withNotify($notify);
        }

        $request->validate([
            'payout_currency_id' => 'required|exists:currencies,id',
            'payout_rate_to_usd' => 'required|numeric|min:0.000000000001',
            'source_ref' => 'nullable|string|max:191',
        ]);

        $result->payout_currency_id = $request->payout_currency_id;
        $result->payout_rate_to_usd = $request->payout_rate_to_usd;
        $result->payout_amount = $result->prize_amount_usd ? bcdiv((string) $result->prize_amount_usd, (string) $request->payout_rate_to_usd, 8) : null;
        $result->source_ref = $request->source_ref;
        $result->paid_at = now();
        $result->save();

        $this->logAudit($result->competition, 'prize_paid', ['user_id' => $result->user_id, 'rank' => $result->rank, 'amount_usd' => $result->prize_amount_usd]);

        $notify[] = ['success', 'Prize marked as paid. Pay the player manually using these converted details.'];
        return back()->withNotify($notify);
    }

    public function disqualify(Request $request, $resultId)
    {
        $result = \App\Models\CompetitionResult::findOrFail($resultId);
        if ($result->competition->hasAnyPaidResult()) {
            $notify[] = ['error', 'Results are locked: at least one prize for this competition has already been paid'];
            return back()->withNotify($notify);
        }

        $request->validate(['reason' => 'required|string|max:255']);
        $result->disqualified_at = now();
        $result->disqualified_reason = $request->reason;
        $result->disqualified_by = auth('admin')->id();
        $result->save();

        $this->logAudit($result->competition, 'disqualified', ['user_id' => $result->user_id, 'reason' => $request->reason]);

        $notify[] = ['success', 'Participant disqualified'];
        return back()->withNotify($notify);
    }

    public function flags()
    {
        $pageTitle = 'Competition Flags';
        $flags = CompetitionFlag::with(['competition:id,title', 'user:id,username', 'relatedUser:id,username'])
            ->orderByDesc('id')->paginate(getPaginate());
        return view('admin.competitions.flags', compact('pageTitle', 'flags'));
    }

    public function reviewFlag(Request $request, $id)
    {
        $flag = CompetitionFlag::findOrFail($id);
        $flag->reviewed_at = now();
        $flag->reviewed_by = auth('admin')->id();
        $flag->save();

        $notify[] = ['success', 'Flag marked as reviewed'];
        return back()->withNotify($notify);
    }

    private function validateCommon(Request $request, bool $isCreate, ?Competition $competition = null): void
    {
        $rules = [
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:2000',
            'rules' => 'nullable|string|max:5000',
            'banner_image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'status' => 'required|in:draft,scheduled,live,ended,finalized,paid',
            'grace_minutes' => 'required|integer|min:0|max:1440',
        ];

        if ($isCreate || !$competition?->isScoringLocked()) {
            $rules = array_merge($rules, [
                'type' => 'required|in:wager_race,biggest_win,lucky_draw,table_league',
                'start_at' => 'required|date',
                'end_at' => 'required|date|after:start_at',
                'min_bet_usd' => 'nullable|numeric|min:0',
                'eligible_games' => 'nullable|string',
                'min_account_age_days' => 'nullable|integer|min:0',
                'min_games' => 'nullable|integer|min:0',
                'min_distinct_opponents' => 'nullable|integer|min:0',
                'max_games_per_opponent_pair' => 'nullable|integer|min:0',
                'ticket_usd_per_ticket' => 'nullable|numeric|min:0',
                'max_tickets_per_user' => 'nullable|integer|min:0',
                'points_win' => 'nullable|numeric',
                'points_draw' => 'nullable|numeric',
                'points_loss' => 'nullable|numeric',
            ]);
        }

        $request->validate($rules);
    }

    private function fillCommon(Competition $competition, Request $request, bool $isCreate): void
    {
        $competition->title = $request->title;
        $competition->slug = $competition->slug ?: $this->uniqueSlug($request->title);
        $competition->description = $request->description;
        $competition->rules = $request->rules;
        $competition->status = $request->status;
        $competition->grace_minutes = $request->grace_minutes;

        if ($request->hasFile('banner_image')) {
            $competition->banner_image = fileUploader($request->file('banner_image'), 'assets/images/competitions', '1200x600', $competition->banner_image ?: null);
        }

        if ($isCreate || !$competition->isScoringLocked()) {
            $competition->type = $request->type;
            $competition->start_at = $request->start_at;
            $competition->end_at = $request->end_at;
            $competition->min_bet_usd = $request->min_bet_usd;
            $competition->eligible_games = $request->filled('eligible_games')
                ? array_values(array_filter(array_map('trim', explode(',', $request->eligible_games))))
                : null;
            $competition->min_account_age_days = $request->min_account_age_days;
            $competition->min_games = $request->min_games;
            $competition->min_distinct_opponents = $request->min_distinct_opponents;
            $competition->max_games_per_opponent_pair = $request->max_games_per_opponent_pair;
            $competition->ticket_usd_per_ticket = $request->ticket_usd_per_ticket;
            $competition->max_tickets_per_user = $request->max_tickets_per_user;

            if ($request->type === Competition::TYPE_TABLE_LEAGUE) {
                $competition->type_settings = [
                    'points' => [
                        'win' => (float) ($request->points_win ?? 3),
                        'draw' => (float) ($request->points_draw ?? 1),
                        'loss' => (float) ($request->points_loss ?? 0),
                    ],
                ];
            }
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'competition';
        $slug = $base;
        $i = 1;
        while (Competition::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    private function syncPrizes(Competition $competition, Request $request, bool $locked = false): void
    {
        if ($locked || !$request->filled('prizes_raw')) {
            return;
        }

        $competition->prizes()->delete();
        foreach (preg_split('/\r?\n/', trim($request->prizes_raw)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            [$rank, $amount] = array_map('trim', array_pad(explode(',', $line), 2, null));
            if (!$rank || !is_numeric($rank) || !is_numeric($amount)) continue;

            CompetitionPrize::create([
                'competition_id' => $competition->id,
                'rank' => (int) $rank,
                'amount_usd' => (float) $amount,
            ]);
        }
    }

    private function logAudit(Competition $competition, string $action, array $details = []): void
    {
        \App\Models\CompetitionAuditLog::create([
            'competition_id' => $competition->id,
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'details' => $details,
        ]);
    }
}
