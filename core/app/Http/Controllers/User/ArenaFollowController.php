<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ArenaFollow;
use App\Models\ArenaNotification;
use App\Models\User;

class ArenaFollowController extends Controller
{
    public function store(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot follow yourself.');

        ArenaFollow::firstOrCreate(['follower_id' => auth()->id(), 'followed_id' => $user->id]);
        ArenaNotification::fire($user->id, 'follow', auth()->id(), null, null, auth()->user()->username . ' started following you');

        return response()->json(['following' => true, 'followers' => $user->arenaFollowers()->count()]);
    }

    public function destroy(User $user)
    {
        ArenaFollow::where('follower_id', auth()->id())->where('followed_id', $user->id)->delete();
        return response()->json(['following' => false, 'followers' => $user->arenaFollowers()->count()]);
    }
}
