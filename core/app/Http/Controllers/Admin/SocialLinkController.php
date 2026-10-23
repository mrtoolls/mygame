<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    public function index()
    {
        $pageTitle = 'Official Social Links';
        $links = SocialLink::orderBy('sort_order')->get();
        return view('admin.social_links.index', compact('pageTitle', 'links'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'url' => 'array',
            'url.*' => 'nullable|url|max:255',
        ]);

        foreach (SocialLink::all() as $link) {
            $link->url = $request->input("url.$link->id");
            $link->is_active = $request->boolean("active.$link->id");
            $link->save();
        }

        $notify[] = ['success', 'Social links updated successfully'];
        return back()->withNotify($notify);
    }
}
