<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\ArenaAnnouncement;
use Illuminate\Http\Request;

class ArenaAnnouncementController extends Controller
{
    public function index()
    {
        $pageTitle = 'Arena Announcements';
        $items     = ArenaAnnouncement::orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.arena.announcements.index', compact('pageTitle', 'items'));
    }

    public function create()
    {
        $pageTitle = 'Add Arena Announcement';
        return view('admin.arena.announcements.create', compact('pageTitle'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:160',
            'description'  => 'nullable|string|max:500',
            'badge_label'  => 'nullable|string|max:40',
            'reward_label' => 'nullable|string|max:60',
            'image'        => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'ends_at'      => 'nullable|date',
            'button_label' => 'nullable|string|max:40',
            'button_url'   => 'nullable|string|max:255',
        ]);

        $item               = new ArenaAnnouncement();
        $item->title        = $request->title;
        $item->description  = $request->description;
        $item->badge_label  = $request->badge_label;
        $item->reward_label = $request->reward_label;
        $item->ends_at      = $request->ends_at;
        $item->button_label = $request->button_label;
        $item->button_url   = $request->button_url;
        $item->status       = Status::ENABLE;
        if ($request->hasFile('image')) {
            $item->image = fileUploader($request->file('image'), 'assets/images/arena-announcements', '1200x400');
        }
        $item->save();

        $notify[] = ['success', 'Announcement added successfully'];
        return to_route('admin.arena.announcements.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $item      = ArenaAnnouncement::findOrFail($id);
        $pageTitle = 'Update Announcement';
        return view('admin.arena.announcements.edit', compact('pageTitle', 'item'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'title'        => 'required|string|max:160',
            'description'  => 'nullable|string|max:500',
            'badge_label'  => 'nullable|string|max:40',
            'reward_label' => 'nullable|string|max:60',
            'image'        => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096',
            'ends_at'      => 'nullable|date',
            'button_label' => 'nullable|string|max:40',
            'button_url'   => 'nullable|string|max:255',
        ]);

        $item               = ArenaAnnouncement::findOrFail($id);
        $item->title        = $request->title;
        $item->description  = $request->description;
        $item->badge_label  = $request->badge_label;
        $item->reward_label = $request->reward_label;
        $item->ends_at      = $request->ends_at;
        $item->button_label = $request->button_label;
        $item->button_url   = $request->button_url;
        if ($request->hasFile('image')) {
            $item->image = fileUploader($request->file('image'), 'assets/images/arena-announcements', '1200x400', $item->image ?: null);
        }
        $item->save();

        $notify[] = ['success', 'Announcement updated successfully'];
        return to_route('admin.arena.announcements.index')->withNotify($notify);
    }

    public function status($id)
    {
        return ArenaAnnouncement::changeStatus($id);
    }

    public function destroy($id)
    {
        $item = ArenaAnnouncement::findOrFail($id);
        if ($item->image) {
            @unlink('assets/images/arena-announcements/' . $item->image);
        }
        $item->delete();
        $notify[] = ['success', 'Announcement deleted successfully'];
        return back()->withNotify($notify);
    }
}
