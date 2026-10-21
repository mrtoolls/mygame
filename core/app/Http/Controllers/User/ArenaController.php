<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ArenaPost;
use App\Models\GameLog;
use Illuminate\Http\Request;

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
}
