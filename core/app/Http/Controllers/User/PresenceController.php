<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UserPresence;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    /**
     * Lightweight "I'm still here" heartbeat for the public "Total Players Online" counter.
     * A dedicated client-triggered ping (not a middleware on every request) so the write
     * frequency is fully controlled client-side - only writes when the existing row is already
     * stale, same throttling lesson as the live-table viewer presence tracker.
     */
    public function ping(Request $request)
    {
        $userId = $request->user()->id;
        $existing = UserPresence::find($userId);
        if (!$existing || !$existing->last_seen_at || $existing->last_seen_at->lt(now()->subSeconds(30))) {
            UserPresence::updateOrCreate(['user_id' => $userId], ['last_seen_at' => now()]);
        }
        return response()->json(['ok' => true]);
    }
}
