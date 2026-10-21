<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
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
        $general                              = gs();
        $general->arena_post_approval_required = $request->status ? Status::ENABLE : Status::DISABLE;
        $general->save();

        $notify[] = ['success', 'Arena settings updated successfully'];
        return back()->withNotify($notify);
    }
}
