<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CurrencyCountryController extends Controller
{
    public function index()
    {
        abort_unless(Schema::hasTable('currencies') && Schema::hasTable('countries'), 503, 'Run the database updater to enable currency and country controls.');

        return view('admin.currency_country.index', [
            'currencies' => Currency::withCount('countries')->orderBy('code')->get(),
            'countries' => Country::with('currency')->orderBy('name')->paginate(100),
            'blockedAttempts' => DB::table('country_block_attempts')->latest('created_at')->paginate(20, ['*'], 'attempts_page'),
            'mismatches' => DB::table('country_access_mismatches')->latest('created_at')->paginate(20, ['*'], 'mismatches_page'),
        ])->with('pageTitle', 'Currencies & Countries');
    }

    public function updateCurrency(Request $request, Currency $currency)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'symbol' => 'required|string|max:12',
            'decimals' => 'required|integer|between:0,3',
            'enabled' => 'required|boolean',
            'auto_update' => 'nullable|boolean',
            'symbol_position' => ['required', Rule::in(['left', 'right'])],
            'rate_to_usd' => 'nullable|numeric|gt:0|max:999999999999',
        ]);

        DB::transaction(function () use ($currency, $data) {
            $locked = Currency::query()->lockForUpdate()->findOrFail($currency->id);
            $newRate = $data['rate_to_usd'] ?? null;
            $oldRate = $locked->rate_to_usd;
            $autoUpdate = (bool) ($data['auto_update'] ?? false);
            $rateToSave = $autoUpdate ? $oldRate : $newRate;
            $locked->fill([
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'decimals' => $data['decimals'],
                'enabled' => $data['enabled'],
                'symbol_position' => $data['symbol_position'],
                'rate_to_usd' => $rateToSave,
                'rate_source' => $autoUpdate ? 'api' : 'manual',
                'auto_update' => $autoUpdate,
                'last_updated_at' => $autoUpdate ? $locked->last_updated_at : ($newRate === null ? null : now()),
            ])->save();

            $normalizeRate = static fn ($rate) => $rate === null ? null : rtrim(rtrim((string) $rate, '0'), '.');
            if ($normalizeRate($oldRate) !== $normalizeRate($rateToSave)) {
                $locked->rateLogs()->create([
                    'old_rate_to_usd' => $oldRate,
                    'new_rate_to_usd' => $rateToSave,
                    'source' => $autoUpdate ? 'api' : 'manual',
                    'changed_by' => auth('admin')->id(),
                ]);
            }
        });

        return back()->withNotify([['success', 'Currency settings saved. Rates affect reports only; wallet balances are not converted.']]);
    }

    public function updateCountry(Request $request, Country $country)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'is_enabled' => 'required|boolean',
            'is_allowed' => 'required|boolean',
            'currency_id' => 'required|integer|exists:currencies,id',
            'phone_code' => 'nullable|string|max:12',
            'allowed_payment_methods' => 'nullable|string|max:1000',
            'min_deposit_minor' => 'required|integer|min:0',
            'max_deposit_minor' => 'required|integer|min:0',
            'min_withdrawal_minor' => 'required|integer|min:0',
            'max_withdrawal_minor' => 'required|integer|min:0',
            'min_table_stake_minor' => 'required|integer|min:0',
            'max_table_stake_minor' => 'required|integer|min:0',
        ]);
        foreach (['deposit', 'withdrawal', 'table_stake'] as $range) {
            if ($data['max_' . $range . '_minor'] > 0 && $data['min_' . $range . '_minor'] > $data['max_' . $range . '_minor']) {
                return back()->withErrors(['min_' . $range . '_minor' => 'The minimum cannot exceed the maximum.'])->withInput();
            }
        }

        $methods = array_values(array_filter(array_map('trim', explode(',', $data['allowed_payment_methods'] ?? ''))));
        $currency = Currency::findOrFail($data['currency_id']);
        if (!$currency->enabled && $data['is_allowed']) {
            return back()->withErrors(['currency_id' => 'An allowed country must use an enabled currency.'])->withInput();
        }

        $country->fill($data);
        $country->allowed_payment_methods = $methods;
        $country->save();

        return back()->withNotify([['success', 'Country settings saved.']]);
    }

    public function addOverride(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id|required_without:ip_address',
            'ip_address' => 'nullable|ip|required_without:user_id',
            'is_allowed' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        DB::table('country_access_overrides')->insert([
            'user_id' => $data['user_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'is_allowed' => $data['is_allowed'],
            'reason' => $data['reason'] ?? null,
            'created_by' => auth('admin')->id(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->withNotify([['success', 'Country access override created.']]);
    }

    public function changeUserCurrency(Request $request, User $user)
    {
        $data = $request->validate([
            'country_id' => 'required|integer|exists:countries,id',
            'reason' => 'required|string|max:500',
        ]);
        $country = Country::with('currency')->findOrFail($data['country_id']);
        abort_unless($country->is_enabled && $country->is_allowed && $country->currency?->enabled, 422, 'The selected country is not available.');

        DB::transaction(function () use ($user, $country, $data) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (!preg_match('/^-?0+(?:\.0+)?$/', trim((string) $locked->balance))) {
                abort(422, 'Settle the user balance to zero before changing wallet currency.');
            }
            if (DB::table('deposits')->where('user_id', $locked->id)->whereIn('status', [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING])->exists()
                || DB::table('withdrawals')->where('user_id', $locked->id)->whereIn('status', [Status::PAYMENT_INITIATE, Status::PAYMENT_PENDING])->exists()) {
                abort(422, 'Resolve pending payment orders before changing wallet currency.');
            }
            if (Schema::hasTable('table_game_players') && DB::table('table_game_players')->where('user_id', $locked->id)->where('status', 'seated')->exists()) {
                abort(422, 'The user must leave all live tables before changing wallet currency.');
            }

            DB::table('user_currency_change_logs')->insert([
                'user_id' => $locked->id,
                'admin_id' => auth('admin')->id(),
                'old_currency_id' => $locked->currency_id,
                'new_currency_id' => $country->currency_id,
                'old_country_id' => $locked->country_id,
                'new_country_id' => $country->id,
                'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            $locked->country_id = $country->id;
            $locked->currency_id = $country->currency_id;
            $locked->save();
        });

        return back()->withNotify([['success', 'User country and wallet currency updated.']]);
    }
}
