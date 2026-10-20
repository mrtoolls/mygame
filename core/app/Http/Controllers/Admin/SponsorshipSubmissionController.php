<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SponsorshipSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SponsorshipSubmissionController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['all', 'pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';
        $query  = SponsorshipSubmission::with(['user', 'moderator'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $submissions = $query->paginate(20)->withQueryString();
        $pageTitle   = 'Sponsorship Submissions';
        return view('admin.sponsorship-submissions.index', compact('pageTitle', 'submissions', 'status'));
    }

    public function show($id)
    {
        $submission = SponsorshipSubmission::with(['user', 'moderator'])->findOrFail($id);
        $pageTitle  = 'Sponsorship Submission';
        return view('admin.sponsorship-submissions.show', compact('pageTitle', 'submission'));
    }

    public function approve($id)
    {
        DB::transaction(function () use ($id) {
            $submission = SponsorshipSubmission::whereKey($id)->lockForUpdate()->firstOrFail();
            $submission->update(['status' => 'approved', 'moderated_by' => auth('admin')->id(), 'approved_at' => now(), 'rejected_at' => null]);
        });
        return back()->withNotify([['success', 'Submission marked as approved.']]);
    }

    public function reject(Request $request, $id)
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);
        DB::transaction(function () use ($id, $data) {
            $submission = SponsorshipSubmission::whereKey($id)->lockForUpdate()->firstOrFail();
            $submission->update(['status' => 'rejected', 'moderated_by' => auth('admin')->id(), 'rejected_at' => now(), 'admin_note' => $data['admin_note'] ?? null]);
        });
        return back()->withNotify([['success', 'Submission rejected.']]);
    }

    public function destroy($id)
    {
        $submission = SponsorshipSubmission::whereKey($id)->firstOrFail();
        if ($submission->logo) {
            @unlink('assets/images/sponsorship-logos/' . $submission->logo);
        }
        $submission->delete();
        return back()->withNotify([['success', 'Submission deleted.']]);
    }
}
