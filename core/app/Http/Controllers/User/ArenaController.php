<?php

namespace App\Http\Controllers\User;

use App\Constants\Status;
use App\Games\TableGames\TableGameService;
use App\Http\Controllers\Controller;
use App\Models\ArenaAnnouncement;
use App\Models\ArenaFollow;
use App\Models\ArenaNotification;
use App\Models\ArenaPost;
use App\Models\ArenaPostVote;
use App\Models\ArenaReaction;
use App\Models\Game;
use App\Models\GameLog;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ArenaController extends Controller
{
    public function index(Request $request)
    {
        $pageTitle = 'Arena';
        $posts     = ArenaPost::approved()->with('user', 'linkedGameLog.game')->latest()->paginate(10);

        $recentWins = [];
        if (auth()->check()) {
            $recentWins = GameLog::win()->where('user_id', auth()->id())
                ->with('game')
                ->latest()
                ->limit(10)
                ->get();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'html'    => view('Template::partials.arena_post_cards', ['posts' => $posts])->render(),
                'hasMore' => $posts->hasMorePages(),
            ]);
        }

        $announcement = ArenaAnnouncement::active()->latest()->first();

        $tableGameService = app(TableGameService::class);
        $catalog           = collect($tableGameService->catalog())->keyBy('key');
        $liveTables        = collect($tableGameService->liveTables(auth()->user()))->take(5);

        $recentSiteWins = GameLog::with('user:id,username', 'game:id,name')
            ->where('win_amo', '>', 0)
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('game_logs', 'demo_play'), fn ($q) => $q->where('demo_play', 0))
            ->latest()
            ->limit(6)
            ->get();

        $trendingGames = Game::active()->where('trending', Status::YES)->limit(5)->get();
        $trendingNews  = NewsPost::published()->orderByDesc('views')->limit(3)->get();

        $userStats = null;
        $currentTier = null;
        $nextTier = null;
        $wagered = 0;
        $tierProgressPercent = 100;
        if (auth()->check()) {
            $user = auth()->user();
            $userStats = [
                'total_invest' => GameLog::where('user_id', $user->id)->sum('invest'),
                'total_win'    => GameLog::win()->where('user_id', $user->id)->sum('invest'),
                'total_loss'   => GameLog::loss()->where('user_id', $user->id)->sum('invest'),
            ];
            $currentTier = $user->vipTier();
            $nextTier    = $user->nextVipTier();
            $wagered     = $user->totalWagered();
            if ($nextTier) {
                $floor = $currentTier?->min_wagered ?? 0;
                $span  = max((float) $nextTier->min_wagered - (float) $floor, 0.00000001);
                $tierProgressPercent = min(100, max(0, (($wagered - $floor) / $span) * 100));
            }
        }

        $suggestedFollows = collect();
        if (auth()->check()) {
            $followingIds = ArenaFollow::where('follower_id', auth()->id())->pluck('followed_id');
            $suggestedFollows = User::where('id', '!=', auth()->id())
                ->whereNotIn('id', $followingIds)
                ->active()
                ->latest()
                ->limit(5)
                ->get();
        }

        return view('Template::arena.index', compact(
            'pageTitle', 'posts', 'recentWins', 'announcement', 'catalog', 'liveTables',
            'recentSiteWins', 'trendingGames', 'trendingNews', 'userStats', 'currentTier',
            'nextTier', 'wagered', 'tierProgressPercent', 'suggestedFollows'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'body'              => 'nullable|string|max:2000',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'background_style'  => 'nullable|string|in:' . implode(',', ArenaPost::BACKGROUNDS),
            'type'              => 'nullable|string|in:' . implode(',', ArenaPost::TYPES),
            'linked_game_log_id' => 'nullable|integer',
        ]);

        if (!$request->filled('body') && !$request->hasFile('image')) {
            $notify[] = ['error', 'Write something or add an image before posting.'];
            return back()->withNotify($notify);
        }

        $linkedGameLogId = null;
        if ($request->filled('linked_game_log_id')) {
            $ownsLog = GameLog::win()->where('user_id', auth()->id())->where('id', $request->linked_game_log_id)->exists();
            if ($ownsLog) {
                $linkedGameLogId = $request->linked_game_log_id;
            }
        }

        $post                    = new ArenaPost();
        $post->user_id           = auth()->id();
        $post->body              = $request->body;
        $post->type              = $request->type ?: 'general';
        $post->linked_game_log_id = $linkedGameLogId;
        if (!$request->hasFile('image')) {
            $post->background_style = $request->background_style ?: ArenaPost::BACKGROUNDS[array_rand(ArenaPost::BACKGROUNDS)];
        }
        if ($request->hasFile('image')) {
            $post->image = fileUploader($request->file('image'), 'assets/images/arena-posts', '1000x1000');
        }
        $post->status = gs('arena_post_approval_required') ? 'pending' : 'approved';
        $post->save();

        $notify[] = ['success', $post->status === 'pending'
            ? 'Your post has been submitted and is awaiting moderator approval.'
            : 'Your post is live on the Arena.'];
        return back()->withNotify($notify);
    }

    public function vote(Request $request, ArenaPost $post)
    {
        $request->validate(['value' => 'required|integer|in:1,-1']);
        $value = (int) $request->value;

        $result = DB::transaction(function () use ($post, $value) {
            $lockedPost = ArenaPost::whereKey($post->id)->lockForUpdate()->first();
            $existing   = ArenaPostVote::where('post_id', $post->id)->where('user_id', auth()->id())->first();

            if ($existing && (int) $existing->value === $value) {
                // Re-clicking the same arrow clears the vote.
                $existing->delete();
                $column = $value === 1 ? 'upvotes_count' : 'downvotes_count';
                $lockedPost->decrement($column);
                $mine = null;
            } elseif ($existing) {
                // Flipping from up to down (or vice versa).
                $existing->value = $value;
                $existing->save();
                $lockedPost->decrement($value === 1 ? 'downvotes_count' : 'upvotes_count');
                $lockedPost->increment($value === 1 ? 'upvotes_count' : 'downvotes_count');
                $mine = $value;
            } else {
                ArenaPostVote::create(['post_id' => $post->id, 'user_id' => auth()->id(), 'value' => $value]);
                $lockedPost->increment($value === 1 ? 'upvotes_count' : 'downvotes_count');
                $mine = $value;
            }

            return ['post' => $lockedPost->fresh(), 'mine' => $mine];
        });

        return response()->json([
            'upvotes'   => $result['post']->upvotes_count,
            'downvotes' => $result['post']->downvotes_count,
            'mine'      => $result['mine'],
        ]);
    }

    public function react(Request $request)
    {
        $request->validate([
            'reactable_type' => 'required|string|in:' . implode(',', array_keys(ArenaReaction::TYPES)),
            'reactable_id'   => 'required|integer',
            'emoji'          => 'required|string|in:' . implode(',', ArenaReaction::EMOJIS),
        ]);

        $modelClass = ArenaReaction::TYPES[$request->reactable_type];
        $target     = $modelClass::find($request->reactable_id);
        abort_unless($target, 404);

        $existing = ArenaReaction::where('reactable_type', $request->reactable_type)
            ->where('reactable_id', $request->reactable_id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing && $existing->emoji === $request->emoji) {
            $existing->delete();
        } else {
            if ($existing) {
                $existing->emoji = $request->emoji;
                $existing->save();
            } else {
                ArenaReaction::create([
                    'reactable_type' => $request->reactable_type,
                    'reactable_id'   => $request->reactable_id,
                    'user_id'        => auth()->id(),
                    'emoji'          => $request->emoji,
                ]);
            }
            ArenaNotification::fire(
                $target->user_id,
                'reaction',
                auth()->id(),
                $request->reactable_type,
                $target->id,
                auth()->user()->username . ' reacted ' . $request->emoji . ' to your ' . $request->reactable_type
            );
        }

        return response()->json(ArenaReaction::summaryFor($request->reactable_type, $request->reactable_id, auth()->id()));
    }

    public function notifications()
    {
        $items = ArenaNotification::where('user_id', auth()->id())->with('actor')->latest()->limit(20)->get();
        return response()->json([
            'html'   => view('Template::partials.arena_notifications', compact('items'))->render(),
            'unread' => ArenaNotification::where('user_id', auth()->id())->unread()->count(),
        ]);
    }

    public function notificationsRead()
    {
        ArenaNotification::where('user_id', auth()->id())->unread()->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function profile(Request $request, $username)
    {
        $profileUser = User::where('username', $username)->firstOrFail();
        $posts       = ArenaPost::approved()->where('user_id', $profileUser->id)->with('user', 'linkedGameLog.game')->latest()->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'html'    => view('Template::partials.arena_post_cards', ['posts' => $posts])->render(),
                'hasMore' => $posts->hasMorePages(),
            ]);
        }

        $pageTitle   = $profileUser->username . ' · Arena';
        $followers   = $profileUser->arenaFollowers()->count();
        $following   = $profileUser->arenaFollowing()->count();
        $isFollowing = auth()->check() ? auth()->user()->isFollowing($profileUser) : false;
        $currentTier = $profileUser->vipTier();

        return view('Template::arena.profile', compact('pageTitle', 'profileUser', 'posts', 'followers', 'following', 'isFollowing', 'currentTier'));
    }

    public function myPosts(Request $request)
    {
        $posts = ArenaPost::where('user_id', auth()->id())->with('linkedGameLog.game')->latest()->paginate(10);

        if ($request->wantsJson()) {
            return response()->json([
                'html'    => view('Template::partials.arena_my_post_cards', ['posts' => $posts])->render(),
                'hasMore' => $posts->hasMorePages(),
            ]);
        }

        $pageTitle = 'My Arena Posts';
        return view('Template::arena.my-posts', compact('pageTitle', 'posts'));
    }
}
