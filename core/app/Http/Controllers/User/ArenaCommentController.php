<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ArenaComment;
use App\Models\ArenaNotification;
use App\Models\ArenaPost;
use Illuminate\Http\Request;

class ArenaCommentController extends Controller
{
    public function index(ArenaPost $post)
    {
        $comments = $post->comments()->with('user', 'replies.user')->get();
        return response()->json([
            'html' => view('Template::partials.arena_comments', compact('post', 'comments'))->render(),
        ]);
    }

    public function store(Request $request, ArenaPost $post)
    {
        $request->validate([
            'body'      => 'required|string|max:1000',
            'parent_id' => 'nullable|integer',
        ]);

        $parentId = null;
        $parent   = null;
        if ($request->filled('parent_id')) {
            // One level of replies only: the parent must itself be a top-level comment on this post.
            $parent = ArenaComment::where('post_id', $post->id)->whereNull('parent_id')->find($request->parent_id);
            if ($parent) {
                $parentId = $parent->id;
            }
        }

        $comment            = new ArenaComment();
        $comment->post_id   = $post->id;
        $comment->user_id   = auth()->id();
        $comment->parent_id = $parentId;
        $comment->body      = $request->body;
        $comment->save();

        $post->increment('comments_count');

        $comment->load('user');

        $actorName = auth()->user()->username;
        if ($parent) {
            ArenaNotification::fire($parent->user_id, 'reply', auth()->id(), 'comment', $comment->id, $actorName . ' replied to your comment');
        } else {
            ArenaNotification::fire($post->user_id, 'comment', auth()->id(), 'post', $post->id, $actorName . ' commented on your post');
        }

        return response()->json([
            'html'      => view('Template::partials.arena_comment_item', ['comment' => $comment, 'post' => $post])->render(),
            'isReply'   => (bool) $parentId,
            'parentId'  => $parentId,
            'comments_count' => $post->fresh()->comments_count,
        ]);
    }
}
