<?php

namespace App\Http\Controllers;

use App\Models\UserFeedback;
use Illuminate\Http\Request;
use Throwable;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(auth()->check(), 401, 'Sign in to leave feedback.');
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'message' => 'required|string|min:15|max:1500',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'public_consent' => 'required|accepted',
        ]);

        $user = auth()->user();
        $image = null;
        if ($request->hasFile('image')) {
            $image = fileUploader($request->file('image'), 'assets/images/user-feedback');
        }

        try {
            UserFeedback::create([
                'user_id' => $user->id,
                'display_name' => mb_substr((string) $user->username, 0, 3) . '***',
                'player_since' => $user->created_at?->year,
                'rating' => $data['rating'],
                'message' => $data['message'],
                'image' => $image,
                'public_consent' => true,
                'status' => 'pending',
            ]);
        } catch (Throwable $e) {
            if ($image) @unlink('assets/images/user-feedback/' . $image);
            throw $e;
        }

        return back()->withNotify([['success', 'Thank you for sharing your feedback. It will appear after admin approval.']]);
    }
}
