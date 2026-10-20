<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\SponsorshipSubmission;
use Illuminate\Http\Request;
use Throwable;

class SponsorshipController extends Controller
{
    public function index()
    {
        $pageTitle   = 'Sponsor Winxpot';
        $submissions = SponsorshipSubmission::where('user_id', auth()->id())->latest()->paginate(10);
        return view('Template::user.sponsorship', compact('pageTitle', 'submissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name'    => 'required|string|max:150',
            'brand_name'   => 'nullable|string|max:150',
            'logo'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'phone'        => 'required|string|max:30',
            'email'        => 'required|email|max:150',
            'message'      => 'required|string|min:30|max:3000',
            'is_anonymous' => 'nullable|boolean',
        ]);

        $logo = null;
        if ($request->hasFile('logo')) {
            $logo = fileUploader($request->file('logo'), 'assets/images/sponsorship-logos');
        }

        try {
            SponsorshipSubmission::create([
                'user_id'      => auth()->id(),
                'full_name'    => $data['full_name'],
                'brand_name'   => $data['brand_name'] ?? null,
                'logo'         => $logo,
                'phone'        => $data['phone'],
                'email'        => $data['email'],
                'message'      => $data['message'],
                'is_anonymous' => $request->boolean('is_anonymous'),
                'status'       => 'pending',
            ]);
        } catch (Throwable $e) {
            if ($logo) {
                @unlink('assets/images/sponsorship-logos/' . $logo);
            }
            throw $e;
        }

        return back()->withNotify([['success', 'Thanks for reaching out. Our team will review your proposal and contact you using the details you provided.']]);
    }
}
