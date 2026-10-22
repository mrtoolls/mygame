<?php

namespace App\Http\Controllers\User\Auth;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\Intended;
use App\Models\AdminNotification;
use App\Models\Country;
use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller {

    use RegistersUsers;

    public function __construct() {
        parent::__construct();
    }

    public function showRegistrationForm() {
        $pageTitle = "Register";
        $countries = Schema::hasTable('countries') ? Country::with('currency')->where('is_enabled', true)->where('is_allowed', true)->orderBy('name')->get() : collect();
        Intended::identifyRoute();
        return view('Template::user.auth.register', compact('pageTitle', 'countries'));
    }

    protected function validator(array $data) {

        $passwordValidation = Password::min(6);

        if (gs('secure_password')) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $agree = 'nullable';
        if (gs('agree')) {
            $agree = 'required';
        }

        $countryRule = Schema::hasTable('countries') ? 'required|integer|exists:countries,id,is_enabled,1,is_allowed,1' : 'nullable|integer';
        $validate = Validator::make($data, [
            'firstname' => 'required',
            'lastname'  => 'required',
            'email'     => 'required|string|email|unique:users',
            'password'  => ['required', 'confirmed', $passwordValidation],
            'country_id' => $countryRule,
            'captcha'   => 'sometimes|required',
            'agree'     => $agree,
        ], [
            'firstname.required' => 'The first name field is required',
            'lastname.required'  => 'The last name field is required',
        ]);

        return $validate;
    }

    public function register(Request $request) {
        if (!gs('registration')) {
            $notify[] = ['error', 'Registration not allowed'];
            return back()->withNotify($notify);
        }
        $this->validator($request->all())->validate();

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        event(new Registered($user = $this->create($request->all())));

        $this->guard()->login($user, true); // persistent remember cookie, matching the login flow

        return $this->registered($request, $user)
        ?: redirect($this->redirectPath());
    }

    protected function create(array $data) {
        $referBy = @$data['referBy'] ?: session()->get('reference');
        if ($referBy) {
            $referUser = User::where('username', $referBy)->first();
        } else {
            $referUser = null;
        }

        //User Create
        $user               = new User();
        $user->email        = strtolower($data['email']);
        $user->firstname    = $data['firstname'];
        $user->lastname     = $data['lastname'];
        $user->password     = Hash::make($data['password']);
        $user->ref_by       = $referUser ? $referUser->id : 0;
        $user->kv           = gs('kv') ? Status::NO : Status::YES;
        $user->ev           = gs('ev') ? Status::NO : Status::YES;
        $user->sv           = gs('sv') ? Status::NO : Status::YES;
        $user->ts           = Status::DISABLE;
        $user->tv           = Status::ENABLE;
        $user->demo_balance = gs('demo_balance') ?? 0;
        if (Schema::hasColumn('users', 'country_id') && !empty($data['country_id'])) {
            $country = Country::with('currency')->where('id', $data['country_id'])->where('is_enabled', true)->where('is_allowed', true)->firstOrFail();
            abort_unless($country->currency?->enabled, 422, 'The selected wallet currency is unavailable.');
            $user->country_id = $country->id;
            $user->currency_id = $country->currency_id;
        }
        $user->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $user->id;
        $adminNotification->title     = 'New member registered';
        $adminNotification->click_url = urlPath('admin.users.detail', $user->id);
        $adminNotification->save();

        //Login Log Create
        $ip        = getRealIP();
        $exist     = UserLogin::where('user_ip', $ip)->first();
        $userLogin = new UserLogin();

        if ($exist) {
            $userLogin->longitude    = $exist->longitude;
            $userLogin->latitude     = $exist->latitude;
            $userLogin->city         = $exist->city;
            $userLogin->country_code = $exist->country_code;
            $userLogin->country      = $exist->country;
        } else {
            $info                    = json_decode(json_encode(getIpInfo()), true);
            $userLogin->longitude    = isset($info['long']) ? implode(',', $info['long']) : '';
            $userLogin->latitude     = isset($info['lat']) ? implode(',', $info['lat']) : '';
            $userLogin->city         = isset($info['city']) ? implode(',', $info['city']) : '';
            $userLogin->country_code = isset($info['code']) ? implode(',', $info['code']) : '';
            $userLogin->country      = isset($info['country']) ? implode(',', $info['country']) : '';
        }

        $userAgent          = osBrowser();
        $userLogin->user_id = $user->id;
        $userLogin->user_ip = $ip;

        $userLogin->browser = @$userAgent['browser'];
        $userLogin->os      = @$userAgent['os_platform'];
        $userLogin->save();

        return $user;
    }

    public function checkUser(Request $request) {
        $exist['data'] = false;
        $exist['type'] = null;
        if ($request->email) {
            $exist['data']  = User::where('email', $request->email)->exists();
            $exist['type']  = 'email';
            $exist['field'] = 'Email';
        }
        if ($request->mobile) {
            $exist['data']  = User::where('mobile', $request->mobile)->where('dial_code', $request->mobile_code)->exists();
            $exist['type']  = 'mobile';
            $exist['field'] = 'Mobile';
        }
        if ($request->username) {
            $exist['data']  = User::where('username', $request->username)->exists();
            $exist['type']  = 'username';
            $exist['field'] = 'Username';
        }
        return response($exist);
    }

    public function registered() {
        return to_route('user.home');
    }
}
