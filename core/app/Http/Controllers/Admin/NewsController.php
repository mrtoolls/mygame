<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    public function index()
    {
        $pageTitle = 'News Posts';
        $posts     = NewsPost::orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.news.index', compact('pageTitle', 'posts'));
    }

    public function create()
    {
        $pageTitle = 'Add News Post';
        return view('admin.news.create', compact('pageTitle'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:160',
            'badge_label' => 'nullable|string|max:40',
            'excerpt'     => 'nullable|string|max:500',
            'body'        => 'required|string',
            'image'       => 'required|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $post               = new NewsPost();
        $post->title        = $request->title;
        $post->slug         = NewsPost::uniqueSlug($request->title);
        $post->badge_label  = $request->badge_label;
        $post->excerpt      = $request->excerpt;
        $post->body         = $request->body;
        $post->status       = Status::ENABLE;
        $post->posted_by_type = 'admin';
        $post->posted_by_id   = auth()->guard('admin')->id();
        $post->posted_by_name = auth()->guard('admin')->user()->username;
        if ($request->hasFile('image')) {
            $post->image = fileUploader($request->file('image'), 'assets/images/news', '1200x600');
        }
        $post->save();

        $notify[] = ['success', 'News post added successfully'];
        return to_route('admin.news.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $post      = NewsPost::findOrFail($id);
        $pageTitle = 'Update News Post';
        return view('admin.news.edit', compact('pageTitle', 'post'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'       => 'required|string|max:160',
            'badge_label' => 'nullable|string|max:40',
            'excerpt'     => 'nullable|string|max:500',
            'body'        => 'required|string',
            'image'       => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
        ]);

        $post              = NewsPost::findOrFail($id);
        if ($post->title !== $request->title) {
            $post->slug = NewsPost::uniqueSlug($request->title, $post->id);
        }
        $post->title       = $request->title;
        $post->badge_label = $request->badge_label;
        $post->excerpt     = $request->excerpt;
        $post->body        = $request->body;
        if ($request->hasFile('image')) {
            $post->image = fileUploader($request->file('image'), 'assets/images/news', '1200x600', $post->image ?: null);
        }
        $post->save();

        $notify[] = ['success', 'News post updated successfully'];
        return to_route('admin.news.index')->withNotify($notify);
    }

    public function status($id)
    {
        return NewsPost::changeStatus($id);
    }

    public function destroy($id)
    {
        $post = NewsPost::findOrFail($id);
        if ($post->image) {
            @unlink('assets/images/news/' . $post->image);
        }
        $post->delete();
        $notify[] = ['success', 'News post deleted successfully'];
        return back()->withNotify($notify);
    }
}
