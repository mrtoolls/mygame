<?php

use App\Constants\Status;
use App\Lib\Captcha;
use App\Lib\ClientInfo;
use App\Lib\FileManager;
use App\Lib\GoogleAuthenticator;
use App\Models\CommissionLog;
use App\Models\Extension;
use App\Models\Frontend;
use App\Models\GeneralSetting;
use App\Models\Language;
use App\Models\Referral;
use App\Models\Transaction;
use App\Models\User;
use App\Notify\Notify;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function systemDetails() {
    $system['name']          = 'winxpot';
    $system['version']       = '3.8';
    $system['build_version'] = '5.1.19';
    return $system;
}

function slug($string) {
    return Str::slug($string);
}

function verificationCode($length) {
    if ($length == 0) {
        return 0;
    }

    $min = pow(10, $length - 1);
    $max = (int) ($min - 1) . '9';
    return random_int($min, $max);
}

function getNumber($length = 8) {
    $characters       = '1234567890';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function activeTemplate($asset = false) {
    $template = session('template') ?? gs('active_template');
    if ($asset) {
        return 'assets/templates/' . $template . '/';
    }

    return 'templates.' . $template . '.';
}

function activeTemplateName() {
    $template = session('template') ?? gs('active_template');
    return $template;
}

function siteLogo($type = null) {
    $name = $type ? "/logo_$type.png" : '/logo.png';
    return getImage(getFilePath('logoIcon') . $name);
}

function siteFavicon() {
    return getImage(getFilePath('logoIcon') . '/favicon.png');
}

function loadReCaptcha() {
    return Captcha::reCaptcha();
}

function loadCustomCaptcha($width = '100%', $height = 46, $bgColor = '#003') {
    return Captcha::customCaptcha($width, $height, $bgColor);
}

function verifyCaptcha() {
    return Captcha::verify();
}

function loadExtension($key) {
    $extension = Extension::where('act', $key)->where('status', Status::ENABLE)->first();
    return $extension ? $extension->generateScript() : '';
}

function getTrx($length = 12) {
    $characters       = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ123456789';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function getAmount($amount, $length = 2) {
    $amount = round($amount ?? 0, $length);
    return $amount + 0;
}

function showAmount($amount, $decimal = 2, $separate = true, $exceptZeros = false, $currencyFormat = true) {
    $separator = '';
    if ($separate) {
        $separator = ',';
    }
    $printAmount = number_format($amount, $decimal, '.', $separator);
    if ($exceptZeros) {
        $exp = explode('.', $printAmount);
        if ($exp[1] * 1 == 0) {
            $printAmount = $exp[0];
        } else {
            $printAmount = rtrim($printAmount, '0');
        }
    }
    if ($currencyFormat) {
        // Show the signed-in viewer's own chosen wallet currency instead of the sitewide
        // default, when they have one set. Switching currency (ProfileController::
        // updateCurrency()) converts the real stored balance, not just a display label, so the
        // amount this function receives is already denominated in that currency - only the
        // symbol/code shown next to it needs to follow, which is the one thing this function
        // never did, leaving every one of its (hundreds of) existing call sites stuck showing
        // the site's own default currency regardless of what a user picked. auth()->check()
        // only resolves the default 'web' (user) guard, so admin-panel screens - a separate
        // guard - are unaffected and keep showing the site default as before.
        $currencySymbol = gs('cur_sym');
        $currencyText = gs('cur_text');
        if (auth()->check() && ($walletCurrency = auth()->user()->walletCurrency) && $walletCurrency->enabled) {
            $currencySymbol = $walletCurrency->symbol;
            $currencyText = $walletCurrency->code;
        }
        if (gs('currency_format') == Status::CUR_BOTH) {
            return $currencySymbol . $printAmount . ' ' . __($currencyText);
        } else if (gs('currency_format') == Status::CUR_TEXT) {
            return $printAmount . ' ' . __($currencyText);
        } else {
            return $currencySymbol . $printAmount;
        }
    }
    return $printAmount;
}

/** Format a legacy major-unit amount or an integer minor-unit amount using a wallet currency. */
function formatCurrencyAmount($amount, $currency = null, bool $amountIsMinor = false, bool $includeCode = true): string {
    if (!$currency && auth()->check()) {
        $currency = auth()->user()->walletCurrency;
    }
    if (is_string($currency)) {
        $currency = \App\Models\Currency::where('code', strtoupper($currency))->first();
    }
    if (!$currency instanceof \App\Models\Currency) {
        return showAmount($amount);
    }

    $money = app(\App\Support\Money::class);
    $minor = $amountIsMinor
        ? (string) $amount
        : $money->toMinor((string) $amount, (int) $currency->decimals);
    $formatted = $currency->formatMinor($minor, false);
    return $includeCode ? $formatted . ' ' . $currency->code : $formatted;
}

/**
 * Converts a USD-denominated amount (how VIP tier rewards are configured, same convention
 * as gift prices in GiftService::quoteInWalletCurrency()) into the given/authenticated
 * user's wallet currency using Currency::rate_to_usd, then formats it with that currency's
 * symbol. Falls back to the site's global currency display when no enabled, rate-current
 * wallet currency is available (guest viewers, currencies pending their daily rate refresh).
 */
function formatUsdInWalletCurrency($usdAmount, $currency = null, bool $includeCode = false): string {
    if (!$currency && auth()->check()) {
        $currency = auth()->user()->currency;
    }
    if (is_string($currency)) {
        $currency = \App\Models\Currency::where('code', strtoupper($currency))->first();
    }
    if (!$currency instanceof \App\Models\Currency || !$currency->enabled || !$currency->rate_to_usd) {
        return showAmount($usdAmount);
    }

    $decimals     = min(8, max(0, (int) $currency->decimals));
    $precision    = max(12, $decimals + 2);
    $walletAmount = bcdiv((string) $usdAmount, (string) $currency->rate_to_usd, $precision);
    $halfUnit     = '0.' . str_repeat('0', $decimals) . '5';
    $rounded      = bcadd($walletAmount, $halfUnit, max(12, $decimals + 1));
    $rounded      = bcadd($rounded, '0', $decimals);
    $minor        = app(\App\Support\Money::class)->toMinor($rounded, $decimals);
    $formatted    = $currency->formatMinor($minor, false);
    return $includeCode ? $formatted . ' ' . $currency->code : $formatted;
}

function removeElement($array, $value) {
    return array_diff($array, (is_array($value) ? $value : array($value)));
}

/**
 * Coin-mode aware balance display - the single place every "show the current user's balance"
 * call site should go through now. When coin_mode is off, this is byte-for-byte the old
 * showAmount($user->balance) call (same string, same formatting). When coin_mode is on, it
 * renders the <x-coin-amount> component against the user's real coin wallet instead - never a
 * stale/cached number, since CoinLedger is the only thing that ever writes to that wallet.
 */
function showUserBalance($user = null, bool $approx = false, bool $showHeld = false): string {
    $user = $user ?: auth()->user();
    if (!$user) return showAmount(0);
    if (!gs('coin_mode')) return showAmount($user->balance);

    $wallet = app(\App\Services\CoinLedger::class)->wallet('user', $user->id);
    return view('components.coin-amount', [
        'available' => $wallet->available_balance,
        'held' => $wallet->held_balance,
        'approx' => $approx,
        'showHeld' => $showHeld,
        'currency' => $user->currency,
    ])->render();
}

/** Plain-text coin amount (no icon/markup) for places HTML can't go - notifications, emails, API strings. */
function coinAmountText($amount): string {
    return number_format((float) $amount, 0) . ' ' . __('Coins');
}

/**
 * For a USD-denominated display amount (gift prices, competition prizes/entry thresholds, VIP
 * perk amounts not already repurposed as raw coins - see formatVipThreshold() for the one that
 * IS) that has no real coin-ledger logic behind it. Converts through the same coin_peg_usd peg
 * CoinPricing uses, so the number shown always matches what that amount would actually cost/pay
 * in coins. Off: unchanged formatUsdInWalletCurrency() behavior.
 */
function formatCoinOrUsd($usdAmount): string {
    if (!gs('coin_mode')) return formatUsdInWalletCurrency($usdAmount);
    $peg = (string) (gs('coin_peg_usd') ?: '0.01');
    return coinAmountText(bcdiv((string) $usdAmount, $peg, 0));
}

/**
 * VIP wagering thresholds (vip_tiers.min_wagered) are the one field User::totalWagered()/
 * vipTier() already repurposes as a literal coin amount once coin_mode is on (no USD
 * conversion at all, by design - see User.php) - this display helper must show the exact same
 * number the backend actually compares against, not a peg-converted one, or the UI and the
 * real threshold would disagree.
 */
function formatVipThreshold($amount): string {
    if (!gs('coin_mode')) return showAmount($amount);
    return coinAmountText($amount);
}

/**
 * For a REAL-CURRENCY amount on an already-recorded historical row (a settlement, a game log, a
 * transaction) that must never be rewritten - converts it to whole coins for display using
 * whichever exchange rate actually applied when that row was made, never today's rate, which
 * would silently change a number that already happened. Resolution order: the rate captured on
 * the row itself ($rateToUsd - wallet_rate_to_usd/table_rate_to_usd/payout_rate_to_usd/etc, the
 * most correct source since it's exactly what the row's own amount was computed with), else the
 * named currency's current rate_to_usd (no per-row rate history is queried here - acceptable for
 * list-style display, and far better than treating the raw number as if it were already USD),
 * else assume the amount is already USD (rate 1) as a last resort when no currency is known at
 * all. Floors to whole coins, same as every other coin conversion in this codebase. Rates are
 * memoized per currency for the lifetime of the request, so a list of many rows (leaderboards,
 * recent-wins tickers) only ever queries each distinct currency once.
 */
function formatHistoricalAmountAsCoins($amount, $currencyId = null, $rateToUsd = null): string {
    if (!gs('coin_mode')) return showAmount($amount);
    static $rateCache = [];
    $rate = $rateToUsd;
    if ((!$rate || bccomp((string) $rate, '0', 12) <= 0) && $currencyId) {
        if (!array_key_exists($currencyId, $rateCache)) {
            $rateCache[$currencyId] = \App\Models\Currency::query()->whereKey($currencyId)->value('rate_to_usd');
        }
        $rate = $rateCache[$currencyId];
    }
    if (!$rate || bccomp((string) $rate, '0', 12) <= 0) $rate = '1';
    $usd = bcmul((string) $amount, (string) $rate, 12);
    $peg = (string) (gs('coin_peg_usd') ?: '0.01');
    return coinAmountText(bcdiv($usd, $peg, 0));
}

function cryptoQR($wallet) {
    return "https://api.qrserver.com/v1/create-qr-code/?data=$wallet&size=300x300&ecc=m";
}

function keyToTitle($text) {
    return ucfirst(preg_replace("/[^A-Za-z0-9 ]/", ' ', $text));
}

function titleToKey($text) {
    return strtolower(str_replace(' ', '_', $text));
}

function strLimit($title = null, $length = 10) {
    return Str::limit($title, $length);
}

function getIpInfo() {
    $ipInfo = ClientInfo::ipInfo();
    return $ipInfo;
}

function osBrowser() {
    $osBrowser = ClientInfo::osBrowser();
    return $osBrowser;
}

function getPageSections($arr = false) {
    $jsonUrl  = resource_path('views/') . str_replace('.', '/', activeTemplate()) . 'sections.json';
    $sections = json_decode(file_get_contents($jsonUrl));
    if ($arr) {
        $sections = json_decode(file_get_contents($jsonUrl), true);
        ksort($sections);
    }
    return $sections;
}

function getImage($image, $size = null) {
    $clean = '';
    if (file_exists($image) && is_file($image)) {
        return asset($image) . $clean;
    }
    if ($size) {
        return route('placeholder.image', $size);
    }
    return asset('assets/images/default.png');
}

function notify($user, $templateName, $shortCodes = null, $sendVia = null, $createLog = true, $pushImage = null) {
    $globalShortCodes = [
        'site_name'       => gs('site_name'),
        'site_currency'   => gs('cur_text'),
        'currency_symbol' => gs('cur_sym'),
    ];

    if (gettype($user) == 'array') {
        $user = (object) $user;
    }

    $shortCodes = array_merge($shortCodes ?? [], $globalShortCodes);

    $notify               = new Notify($sendVia);
    $notify->templateName = $templateName;
    $notify->shortCodes   = $shortCodes;
    $notify->user         = $user;
    $notify->createLog    = $createLog;
    $notify->pushImage    = $pushImage;
    $notify->userColumn   = isset($user->id) ? $user->getForeignKey() : 'user_id';
    $notify->send();
}

function getPaginate($paginate = null) {
    if (!$paginate) {
        $paginate = gs('paginate_number');
    }
    return $paginate;
}

function paginateLinks($data, $view = null) {
    return $data->appends(request()->all())->links($view);
}

function menuActive($routeName, $type = null, $param = null) {
    if ($type == 3) {
        $class = 'side-menu--open';
    } else if ($type == 2) {
        $class = 'sidebar-submenu__open';
    } else {
        $class = 'active';
    }

    if (is_array($routeName)) {
        foreach ($routeName as $key => $value) {
            if (request()->routeIs($value)) {
                return $class;
            }

        }
    } else if (request()->routeIs($routeName)) {
        $routeParam = array_values(isset(request()->route()->parameters) ? request()->route()->parameters : []);
        $firstParam = $routeParam[0] ?? null;
        if ($param) {
            if (is_string($firstParam) && is_string($param) && strcasecmp($firstParam, $param) === 0) {
                return $class;
            }
        } else {
            return $class;
        }
    }
}

function fileUploader($file, $location, $size = null, $old = null, $thumb = null, $filename = null) {
    $fileManager           = new FileManager($file);
    $fileManager->path     = $location;
    $fileManager->size     = $size;
    $fileManager->old      = $old;
    $fileManager->thumb    = $thumb;
    $fileManager->filename = $filename;
    $fileManager->upload();
    return $fileManager->filename;
}

function fileManager() {
    return new FileManager();
}

function getFilePath($key) {
    return fileManager()->$key()->path;
}

function getFileSize($key) {
    return fileManager()->$key()->size;
}

function getFileExt($key) {
    return fileManager()->$key()->extensions;
}

function diffForHumans($date) {
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }
    Carbon::setlocale($lang);
    return Carbon::parse($date)->diffForHumans();
}

function showDateTime($date, $format = 'Y-m-d h:i A') {
    if (!$date) {
        return '-';
    }
    $lang = session()->get('lang');
    if (!$lang) {
        $lang = getDefaultLang();
    }
    Carbon::setlocale($lang);
    return Carbon::parse($date)->translatedFormat($format);
}
function getDefaultLang() {
    return Language::where('is_default', Status::YES)->first()->code ?? 'en';
}

function getContent($dataKeys, $singleQuery = false, $limit = null, $orderById = false) {

    $templateName = activeTemplateName();
    if ($singleQuery) {
        $content = Frontend::where('tempname', $templateName)->where('data_keys', $dataKeys)->orderBy('id', 'desc')->first();
    } else {
        $article = Frontend::where('tempname', $templateName);
        $article->when($limit != null, function ($q) use ($limit) {
            return $q->limit($limit);
        });
        if ($orderById) {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id')->get();
        } else {
            $content = $article->where('data_keys', $dataKeys)->orderBy('id', 'desc')->get();
        }
    }
    return $content;
}

function verifyG2fa($user, $code, $secret = null) {
    $authenticator = new GoogleAuthenticator();
    if (!$secret) {
        $secret = $user->tsc;
    }
    $oneCode  = $authenticator->getCode($secret);
    $userCode = $code;
    if ($oneCode == $userCode) {
        $user->tv = Status::YES;
        $user->save();
        return true;
    } else {
        return false;
    }
}

function urlPath($routeName, $routeParam = null) {
    if ($routeParam == null) {
        $url = route($routeName);
    } else {
        $url = route($routeName, $routeParam);
    }
    $basePath = route('home');
    $path     = str_replace($basePath, '', $url);
    return $path;
}

function showMobileNumber($number) {
    $length = strlen($number);
    return substr_replace($number, '***', 2, $length - 4);
}

function showEmailAddress($email) {
    $endPosition = strpos($email, '@') - 1;
    return substr_replace($email, '***', 1, $endPosition);
}

function getRealIP() {
    $ip = $_SERVER["REMOTE_ADDR"];
    //Deep detect ip
    if (filter_var(@$_SERVER['HTTP_FORWARDED'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    }
    if (filter_var(@$_SERVER['HTTP_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    if (filter_var(@$_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if ($ip == '::1') {
        $ip = '127.0.0.1';
    }

    return $ip;
}

function appendQuery($key, $value) {
    return request()->fullUrlWithQuery([$key => $value]);
}

function dateSort($a, $b) {
    return strtotime($a) - strtotime($b);
}

function dateSorting($arr) {
    usort($arr, "dateSort");
    return $arr;
}

function gs($key = null) {
    try {
        $general = Cache::get('GeneralSetting');
    } catch (\Throwable) {
        // A fresh install may not have the configured database cache table yet.
        $general = null;
    }
    if (!$general) {
        $general = GeneralSetting::first();
        try {
            Cache::put('GeneralSetting', $general);
        } catch (\Throwable) {
            // Keep settings available while the admin's Database Update creates cache tables.
        }
    }
    if ($key) {
        return @$general->$key;
    }
    return $general;
}
function isImage($string) {
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif');
    $fileExtension     = pathinfo($string, PATHINFO_EXTENSION);
    if (in_array($fileExtension, $allowedExtensions)) {
        return true;
    } else {
        return false;
    }
}

function isHtml($string) {
    if (preg_match('/<.*?>/', $string)) {
        return true;
    } else {
        return false;
    }
}

function convertToReadableSize($size) {
    preg_match('/^(\d+)([KMG])$/', $size, $matches);
    $size = (int) $matches[1];
    $unit = $matches[2];

    if ($unit == 'G') {
        return $size . 'GB';
    }
    if ($unit == 'M') {
        return $size . 'MB';
    }
    if ($unit == 'K') {
        return $size . 'KB';
    }
    return $size . $unit;
}

function frontendImage($sectionName, $image, $size = null, $seo = false) {
    if ($seo) {
        return getImage('assets/images/frontend/' . $sectionName . '/seo/' . $image, $size);
    }
    return getImage('assets/images/frontend/' . $sectionName . '/' . $image, $size);
}

function levelCommission($id, $amount, $commissionType = '') {
    $usr   = $id;
    $i     = 1;
    $gnl   = gs();
    $level = Referral::count();

    while ($usr != "" || $usr != "0" || $i < $level) {
        $me    = User::find($usr);
        $refer = User::find($me->ref_by);
        if ($refer == "") {
            break;
        }

        $commission = Referral::where('level', $i)->first();
        if ($commission == null) {
            break;
        }

        $com = bcdiv(bcmul((string) $amount, (string) $commission->percent, 8), '100', 8);

        // Locked and re-read under the lock (not the already-stale $refer row from above) so
        // two commission-triggering events for the same referrer landing at the same time can
        // never both read the same starting balance and silently drop one credit.
        [$newBal, $trx] = DB::transaction(function () use ($refer, $id, $i, $com, $commissionType) {
            $referWallet = User::whereKey($refer->id)->lockForUpdate()->first();
            $newBal      = getAmount(bcadd((string) $referWallet->balance, $com, 8));
            $referWallet->balance = $newBal;
            $referWallet->save();
            $trx = getTrx();

            $transaction               = new Transaction();
            $transaction->user_id      = $refer->id;
            $transaction->amount       = getAmount($com);
            $transaction->charge       = 0;
            $transaction->trx_type     = '+';
            $transaction->remark       = 'commission';
            $transaction->details      = $i . ' level Referral Commission';
            $transaction->trx          = $trx;
            $transaction->post_balance = $newBal;
            $transaction->save();

            $commission           = new CommissionLog();
            $commission->user_id  = $refer->id;
            $commission->who      = $id;
            $commission->level    = $i . ' level Referral Commission';
            $commission->amount   = getAmount($com);
            $commission->main_amo = $newBal;
            $commission->title    = $commissionType;
            $commission->trx      = $trx;
            $commission->save();

            return [$newBal, $trx];
        });

        notify($refer, 'REFERRAL_COMMISSION', [
            'amount'       => getAmount($com),
            'post_balance' => $newBal,
            'trx'          => $trx,
            'level'        => $i . ' level Referral Commission',
            'currency'     => $gnl->cur_text,
        ]);
        $usr = $refer->id;
        $i++;
    }
    return 0;
}

function buildResponse($remark, $status, $notify, $data = null) {
    $response = [
        'remark' => $remark,
        'status' => $status,
    ];

    $message = [];

    if ($notify instanceof \Illuminate\Support\MessageBag) {
        $message['error'] = collect($notify)->map(function ($item) {
            return $item[0];
        })->values()->toArray();
    } else {
        $message = [$status => collect($notify)->map(function ($item) {
            if (is_string($item)) {
                return $item;
            }
            if (count($item) > 1) {
                return $item[1];
            }
            return $item[0];
        })->toArray()];
    }

    $response['message'] = $message;
    if ($data) {
        $response['data'] = $data;
    }
    return response()->json($response);
}

function responseSuccess($remark, $notify, $data = null) {
    return buildResponse($remark, 'success', $notify, $data);
}

function responseError($remark, $notify, $data = null) {
    return buildResponse($remark, 'error', $notify, $data);
}
