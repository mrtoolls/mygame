<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller {
    public function profile() {
        $pageTitle  = "Profile Setting";
        $user       = auth()->user();
        $currencies = Currency::where('enabled', true)->orderBy('code')->get();
        return view('Template::user.profile_setting', compact('pageTitle', 'user', 'currencies'));
    }

    public function submitProfile(Request $request) {
        $user = auth()->user();

        $request->validate([
            'firstname' => 'required|string',
            'lastname'  => 'required|string',
            'username'  => 'required|string|min:3|max:60|regex:/^[a-z0-9_]+$/|unique:users,username,' . $user->id,
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'firstname.required' => 'The first name field is required',
            'lastname.required'  => 'The last name field is required',
            'username.regex'     => 'Username can contain only lowercase letters, numbers and underscore.',
        ]);

        $user->firstname = $request->firstname;
        $user->lastname  = $request->lastname;
        $user->username  = $request->username;

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

    public function avatarPicker() {
        $pageTitle = 'Choose Your Avatar';
        $user      = auth()->user();
        $gender    = \App\Support\AvatarLibrary::normalizeGender($user->gender);
        $avatars   = \App\Support\AvatarLibrary::filenames($gender);
        return view('Template::user.avatar_picker', compact('pageTitle', 'user', 'gender', 'avatars'));
    }

    public function avatarSelect(Request $request) {
        $user   = auth()->user();
        $gender = \App\Support\AvatarLibrary::normalizeGender($user->gender);

        $request->validate(['avatar' => 'required|string']);

        if (!\App\Support\AvatarLibrary::isValid($gender, $request->avatar)) {
            $notify[] = ['error', 'Please pick a valid avatar from the list.'];
            return back()->withNotify($notify);
        }

        $user->image = \App\Support\AvatarLibrary::pathFor($gender, $request->avatar);
        $user->save();

        $notify[] = ['success', 'Avatar updated successfully'];
        return back()->withNotify($notify);
    }

    // Switching currency re-prices balance/demo_balance through USD (the same rate_to_usd
    // mechanism TableGameService already uses for cross-currency stakes) so the real-world
    // value of a user's funds survives the switch - the stored figure changes, not what it's
    // worth. Row-locked and wrapped in one transaction so a deposit landing mid-conversion
    // can't interleave with it.
    public function updateCurrency(Request $request) {
        $request->validate(['currency_id' => 'required|exists:currencies,id']);
        $newCurrency = Currency::where('id', $request->currency_id)->where('enabled', true)->first();
        if (!$newCurrency) {
            $notify[] = ['error', 'That currency is not available.'];
            return back()->withNotify($notify);
        }
        if (!$newCurrency->rate_to_usd || bccomp((string) $newCurrency->rate_to_usd, '0', 12) <= 0) {
            $notify[] = ['error', 'That currency does not have a current exchange rate available.'];
            return back()->withNotify($notify);
        }

        DB::transaction(function () use ($request, $newCurrency) {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ((int) $user->currency_id === (int) $newCurrency->id) return;

            $oldCurrency = $user->currency_id ? Currency::find($user->currency_id) : null;
            $oldRate = $oldCurrency?->rate_to_usd;

            foreach (['balance', 'demo_balance'] as $field) {
                $amount = (string) ($user->{$field} ?? '0');
                if (bccomp($amount, '0', 8) === 0) continue;

                // amount * rate_to_usd = USD - the same direction used for locked table stakes.
                $usdValue = $oldRate ? bcmul($amount, (string) $oldRate, 12) : $amount;
                $converted = bcdiv($usdValue, (string) $newCurrency->rate_to_usd, 8);
                $user->{$field} = $converted;

                if ($field === 'balance' && bccomp($converted, $amount, 8) !== 0) {
                    $transaction = new Transaction();
                    $transaction->user_id = $user->id;
                    $transaction->amount = bcsub($converted, $amount, 8);
                    $transaction->charge = 0;
                    $transaction->trx_type = bccomp($converted, $amount, 8) >= 0 ? '+' : '-';
                    $transaction->details = 'Balance converted from ' . ($oldCurrency->code ?? 'USD') . ' to ' . $newCurrency->code . ' after changing your preferred currency';
                    $transaction->remark = 'currency_change';
                    $transaction->trx = strtoupper(Str::random(12));
                    $transaction->post_balance = $converted;
                    if ($this->hasColumn('transactions', 'currency_id')) $transaction->currency_id = $newCurrency->id;
                    $transaction->save();
                }
            }

            $user->currency_id = $newCurrency->id;
            $user->save();
        });

        $notify[] = ['success', 'Your preferred currency has been updated to ' . $newCurrency->code . '.'];
        return back()->withNotify($notify);
    }

    private function hasColumn(string $table, string $column): bool {
        return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
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
