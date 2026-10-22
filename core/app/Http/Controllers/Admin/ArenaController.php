<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\ArenaNotification;
use App\Models\ArenaPost;
use Illuminate\Http\Request;

class ArenaController extends Controller
{
    public function settings()
    {
        $pageTitle = 'Arena Settings';
        return view('admin.arena.settings', compact('pageTitle'));
    }

    public function settingsUpdate(Request $request)
    {
        $request->validate(['arena_video_max_mb' => 'required|integer|min:1|max:500']);

        $general                               = gs();
        $general->arena_post_approval_required = $request->status ? Status::ENABLE : Status::DISABLE;
        $general->arena_video_max_mb           = $request->arena_video_max_mb;
        $general->save();

        $notify[] = ['success', 'Arena settings updated successfully'];
        return back()->withNotify($notify);
    }

    public function pending()
    {
        $pageTitle = 'Pending Arena Posts';
        $posts     = ArenaPost::pending()->with('user')->latest()->paginate(getPaginate());
        return view('admin.arena.pending', compact('pageTitle', 'posts'));
    }

    public function approve($id)
    {
        $post = ArenaPost::where('id', $id)->where('status', 'pending')->firstOrFail();
        $post->status             = 'approved';
        $post->moderated_by_type  = 'admin';
        $post->moderated_by_id    = auth()->guard('admin')->id();
        $post->save();

        ArenaNotification::fire($post->user_id, 'post_approved', null, 'post', $post->id, 'Your Arena post was approved and is now live.');

        $notify[] = ['success', 'Post approved successfully'];
        return back()->withNotify($notify);
    }

    public function reject(Request $request)
    {
        $request->validate(['id' => 'required|integer', 'message' => 'required|string|max:255']);
        $post = ArenaPost::where('id', $request->id)->where('status', 'pending')->firstOrFail();
        $post->status             = 'rejected';
        $post->rejection_reason   = $request->message;
        $post->moderated_by_type  = 'admin';
        $post->moderated_by_id    = auth()->guard('admin')->id();
        $post->save();

        ArenaNotification::fire($post->user_id, 'post_rejected', null, 'post', $post->id, 'Your Arena post was rejected: ' . $request->message);

        $notify[] = ['success', 'Post rejected successfully'];
        return back()->withNotify($notify);
    }
}
