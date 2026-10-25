<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Support\MoneyConversionAudit;
use App\Services\CurrencyRateRefresher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CurrencyCountryController extends Controller
{
    public function moneyAudit(MoneyConversionAudit $audit)
    {
        return view('admin.currency_country.money_audit', [
            'pageTitle' => 'Money Conversion Audit',
            'report' => $audit->run(),
        ]);
    }

    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('currencies') && Schema::hasTable('countries'), 503, 'Run the database updater to enable currency and country controls.');

        $countryQuery = Country::with('currency')->orderBy('name');
        if ($search = trim((string) $request->query('country_search'))) $countryQuery->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('iso_code', 'like', "%{$search}%"));
        if ($request->query('access') === 'allowed') $countryQuery->where('is_enabled', true)->where('is_allowed', true);
        elseif ($request->query('access') === 'blocked') $countryQuery->where(fn ($q) => $q->where('is_enabled', false)->orWhere('is_allowed', false));
        if ($currencyId = $request->query('country_currency')) $countryQuery->where('currency_id', $currencyId);
        if ($request->query('coin_marketplace') === 'on') $countryQuery->where('coin_marketplace_enabled', true);
        elseif ($request->query('coin_marketplace') === 'off') $countryQuery->where('coin_marketplace_enabled', false);

        $attemptQuery = DB::table('country_block_attempts')->latest('created_at');
        if ($request->filled('attempt_country')) {
            $country = strtoupper((string) $request->query('attempt_country'));
            $country === 'UNKNOWN' ? $attemptQuery->whereNull('detected_country_code') : $attemptQuery->where('detected_country_code', $country);
        }
        if ($request->query('attempt_reason') === 'missing_signal') $attemptQuery->where('reason', 'like', '%no usable CF-IPCountry%');
        elseif ($request->query('attempt_reason') === 'country_block') $attemptQuery->where(fn ($q) => $q->where('reason', 'like', '%blocked or unavailable%')->orWhere('reason', 'like', '%signup country is blocked%'));
        elseif ($request->query('attempt_reason') === 'override') $attemptQuery->where('reason', 'like', '%override%');

        $settings = gs();
        $hasApiKey = false;
        try { $hasApiKey = (bool) $settings->currency_rate_api_key && Crypt::decryptString($settings->currency_rate_api_key) !== ''; } catch (\Throwable) {}
        $activeCoinCurrencyIds = Schema::hasTable('coin_rates')
            ? DB::table('coin_rates')->where('status', 'active')->whereNotNull('buy_price')->pluck('currency_id')->all()
            : [];

        return view('admin.currency_country.index', [
            'currencies' => Currency::withCount('countries')->orderBy('code')->get(),
            'countries' => $countryQuery->paginate(100)->withQueryString(),
            'countryAllowedCount' => Country::where('is_enabled', true)->where('is_allowed', true)->count(),
            'countryBlockedCount' => Country::where(fn ($q) => $q->where('is_enabled', false)->orWhere('is_allowed', false))->count(),
            'countryCoinMarketplaceOnCount' => Schema::hasColumn('countries', 'coin_marketplace_enabled') ? Country::where('coin_marketplace_enabled', true)->count() : 0,
            'blockedAttempts' => $attemptQuery->paginate(20, ['*'], 'attempts_page')->withQueryString(),
            'mismatches' => DB::table('country_access_mismatches')->latest('created_at')->paginate(20, ['*'], 'mismatches_page'),
            'rateSettings' => $settings,
            'hasApiKey' => $hasApiKey,
            'activeCoinCurrencyIds' => $activeCoinCurrencyIds,
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
            'rate_reason' => 'nullable|string|required_without:auto_update|max:500',
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
                        'reason' => $data['rate_reason'] ?? null,
                ]);
            }
        });

        return back()->withNotify([['success', 'Currency settings saved. Wallet balances are unchanged; automatic rates refresh once a day.']]);
    }

    public function refreshRates(CurrencyRateRefresher $refresher)
    {
        try {
            $result = $refresher->refresh(auth('admin')->id());
            $summary = "Rates refreshed from the {$result['source']} endpoint; {$result['updated']} currencies checked and coin prices recomputed.";
            if ($result['fallback_notice']) $summary .= ' The keyed endpoint failed, so the free endpoint was used.';
            return back()->withNotify([['success', $summary]]);
        } catch (\Throwable $exception) {
            return back()->withNotify([['error', 'Rates could not be refreshed: ' . $exception->getMessage()]]);
        }
    }

    public function saveRateSource(Request $request)
    {
        $data = $request->validate(['api_key' => 'nullable|string|max:500', 'clear_api_key' => 'nullable|boolean']);
        $settings = gs();
        if ($request->boolean('clear_api_key')) $settings->currency_rate_api_key = null;
        elseif (trim((string) ($data['api_key'] ?? '')) !== '') $settings->currency_rate_api_key = Crypt::encryptString(trim($data['api_key']));
        $settings->save();
        return back()->withNotify([['success', 'Rate source settings saved. The stored API key is encrypted and cannot be viewed here.']]);
    }

    public function manualRateOverride(Request $request, Currency $currency)
    {
        $data = $request->validate(['rate_to_usd' => 'required|numeric|gt:0|max:999999999999', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($currency, $data) {
            $locked = Currency::whereKey($currency->id)->lockForUpdate()->firstOrFail();
            $oldRate = $locked->rate_to_usd;
            $locked->rate_to_usd = $data['rate_to_usd'];
            $locked->rate_source = 'manual';
            $locked->auto_update = false;
            $locked->last_updated_at = now();
            $locked->save();
            $locked->rateLogs()->create(['old_rate_to_usd' => $oldRate, 'new_rate_to_usd' => $data['rate_to_usd'], 'source' => 'manual', 'changed_by' => auth('admin')->id(), 'reason' => $data['reason']]);
        });
        if (Schema::hasTable('coin_rates')) {
            try { app(\App\Services\CoinPricing::class)->recompute($currency->fresh(), auth('admin')->id()); }
            catch (\InvalidArgumentException $exception) { return back()->withNotify([['warning', "Manual {$currency->code} rate saved; coin price needs review: {$exception->getMessage()}"]]); }
        }
        return back()->withNotify([['success', "Manual {$currency->code} rate saved and logged. Automatic refresh is off for this currency."]]);
    }

    public function bulkCountries(Request $request)
    {
        $data = $request->validate(['country_ids' => 'required|array|min:1', 'country_ids.*' => 'integer|exists:countries,id', 'bulk_action' => 'required|in:allow,block,set_currency,marketplace_on,marketplace_off', 'currency_id' => 'required_if:bulk_action,set_currency|nullable|integer|exists:currencies,id']);
        if ($data['bulk_action'] === 'set_currency') {
            abort_unless(Currency::whereKey($data['currency_id'])->where('enabled', true)->exists(), 422, 'Choose an enabled currency.');
            Country::whereIn('id', $data['country_ids'])->update(['currency_id' => $data['currency_id']]);
        } elseif (in_array($data['bulk_action'], ['marketplace_on', 'marketplace_off'], true)) {
            abort_unless(Schema::hasColumn('countries', 'coin_marketplace_enabled'), 503, 'Run the database updater to enable marketplace country controls.');
            Country::whereIn('id', $data['country_ids'])->update(['coin_marketplace_enabled' => $data['bulk_action'] === 'marketplace_on']);
        } else {
            Country::whereIn('id', $data['country_ids'])->update(['is_enabled' => true, 'is_allowed' => $data['bulk_action'] === 'allow']);
        }
        return back()->withNotify([['success', count($data['country_ids']) . ' country settings updated.']]);
    }

    public function updateCountry(Request $request, Country $country)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'is_enabled' => 'required|boolean',
            'is_allowed' => 'required|boolean',
            'coin_marketplace_enabled' => 'required|boolean',
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
            if (Schema::hasTable('business_promotions') && DB::table('business_promotions')->where('user_id', $locked->id)->where('status', 'pending')->exists()) {
                abort(422, 'Resolve pending paid business promotions before changing wallet currency.');
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
