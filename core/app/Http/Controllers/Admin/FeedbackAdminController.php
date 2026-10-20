<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';
        $query = UserFeedback::with(['user', 'moderator'])->latest();
        if ($status !== 'all') $query->where('status', $status);
        $feedbacks = $query->paginate(20)->withQueryString();
        $pageTitle = 'Feedback Moderation';
        return view('admin.feedback.index', compact('pageTitle', 'feedbacks', 'status'));
    }

    public function approve($id)
    {
        // Doubles as "show": a previously-rejected/hidden entry can be approved (made visible)
        // again, not just a pending one - moderation here is a reversible toggle, not one-way.
        DB::transaction(function () use ($id) {
            $feedback = UserFeedback::whereKey($id)->lockForUpdate()->firstOrFail();
            $feedback->update(['status' => 'approved', 'moderated_by' => auth('admin')->id(), 'approved_at' => now(), 'rejected_at' => null, 'admin_note' => null]);
        });
        return back()->withNotify([['success', 'Feedback approved and published.']]);
    }

    public function reject(Request $request, $id)
    {
        // Doubles as "hide": moving an already-approved entry back to rejected takes it off the
        // public feedback sections without deleting it.
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);
        DB::transaction(function () use ($id, $data) {
            $feedback = UserFeedback::whereKey($id)->lockForUpdate()->firstOrFail();
            $feedback->update(['status' => 'rejected', 'moderated_by' => auth('admin')->id(), 'rejected_at' => now(), 'admin_note' => $data['admin_note'] ?? null]);
        });
        return back()->withNotify([['success', 'Feedback hidden from public view.']]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'display_name' => 'required|string|max:80',
            'player_since' => 'nullable|integer|min:1900|max:' . date('Y'),
            'rating' => 'required|integer|min:1|max:5',
            'message' => 'required|string|max:1500',
        ]);
        UserFeedback::whereKey($id)->firstOrFail()->update($data);
        return back()->withNotify([['success', 'Feedback updated.']]);
    }

    public function destroy($id)
    {
        UserFeedback::whereKey($id)->firstOrFail()->delete();
        return back()->withNotify([['success', 'Feedback deleted.']]);
    }
}
