<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller {
    public function profile() {
        $pageTitle = "Profile Setting";
        $user      = auth()->user();
        return view('Template::user.profile_setting', compact('pageTitle', 'user'));
    }

    public function submitProfile(Request $request) {
        $request->validate([
            'firstname' => 'required|string',
            'lastname'  => 'required|string',
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'firstname.required' => 'The first name field is required',
            'lastname.required'  => 'The last name field is required',
        ]);

        $user = auth()->user();

        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;

        $user->address = $request->address;
        $user->city    = $request->city;
        $user->state   = $request->state;
        $user->zip     = $request->zip;

        if ($request->hasFile('image')) {
            // Only delete the old file when it's a previous custom upload of this user's own -
            // never a shared default avatar from assets/images/profilepics, which other users
            // may still be using.
            $oldImage = $user->image && str_starts_with($user->image, 'assets/images/user_profile/')
                ? basename($user->image)
                : null;
            $filename    = fileUploader($request->image, 'assets/images/user_profile', '480x480', $oldImage);
            $user->image = 'assets/images/user_profile/' . $filename;
        }

        $user->save();
        $notify[] = ['success', 'Profile updated successfully'];
        return back()->withNotify($notify);
    }

    public function changePassword() {
        $pageTitle = 'Change Password';
        return view('Template::user.password', compact('pageTitle'));
    }

    public function submitPassword(Request $request) {

        $passwordValidation = Password::min(6);
        if (gs('secure_password')) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'confirmed', $passwordValidation],
        ]);

        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            $password       = Hash::make($request->password);
            $user->password = $password;
            $user->save();
            $notify[] = ['success', 'Password changed successfully'];
            return back()->withNotify($notify);
        } else {
            $notify[] = ['error', 'The password doesn\'t match!'];
            return back()->withNotify($notify);
        }
    }
}
