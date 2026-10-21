<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ArenaPost;
use App\Models\ArenaPostVote;
use App\Models\ArenaReaction;
use App\Models\GameLog;
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

        return view('Template::arena.index', compact('pageTitle', 'posts', 'recentWins'));
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
        abort_unless($modelClass::whereKey($request->reactable_id)->exists(), 404);

        $existing = ArenaReaction::where('reactable_type', $request->reactable_type)
            ->where('reactable_id', $request->reactable_id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing && $existing->emoji === $request->emoji) {
            $existing->delete();
        } elseif ($existing) {
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

        return response()->json(ArenaReaction::summaryFor($request->reactable_type, $request->reactable_id, auth()->id()));
    }
}
