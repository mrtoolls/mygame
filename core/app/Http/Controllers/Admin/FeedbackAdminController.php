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
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';
        $feedbacks = UserFeedback::with(['user', 'moderator'])->where('status', $status)->latest()->paginate(20)->withQueryString();
        return view('admin.feedback.index', compact('feedbacks', 'status'));
    }

    public function approve($id)
    {
        DB::transaction(function () use ($id) {
            $feedback = UserFeedback::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($feedback->status !== 'pending', 409, 'Only pending feedback can be approved.');
            $feedback->update(['status' => 'approved', 'moderated_by' => auth('admin')->id(), 'approved_at' => now(), 'admin_note' => null]);
        });
        return back()->withNotify([['success', 'Feedback approved and published.']]);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);
        DB::transaction(function () use ($id, $data) {
            $feedback = UserFeedback::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($feedback->status !== 'pending', 409, 'Only pending feedback can be rejected.');
            $feedback->update(['status' => 'rejected', 'moderated_by' => auth('admin')->id(), 'rejected_at' => now(), 'admin_note' => $data['admin_note'] ?? null]);
        });
        return back()->withNotify([['success', 'Feedback rejected.']]);
    }
}
