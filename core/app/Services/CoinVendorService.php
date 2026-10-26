<?php

namespace App\Services;

use App\Models\CoinRate;
use App\Models\CoinVendor;
use App\Models\CoinVendorApplication;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CoinVendorService
{
    public function eligibility(User $user): array
    {
        $checks = [];
        $checks[] = ['label' => 'Email address verified', 'ok' => (int) $user->ev === 1, 'message' => (int) $user->ev === 1 ? 'Your email is verified.' : 'Verify your email before applying.'];
        $checks[] = ['label' => 'Phone number verified', 'ok' => (int) $user->sv === 1, 'message' => (int) $user->sv === 1 ? 'Your phone is verified.' : 'Verify the phone number on your account first.'];
        $ageDays = max(0, (int) ($user->created_at?->diffInDays(now()) ?? 0));
        $minimumAge = (int) (gs('coin_vendor_min_account_age_days') ?? 7);
        $checks[] = ['label' => 'Account age', 'ok' => $ageDays >= $minimumAge, 'message' => $ageDays >= $minimumAge ? "Account age: {$ageDays} days." : "Your account must be at least {$minimumAge} days old. ({$ageDays} days so far.)"];
        $country = $user->country;
        $countryOk = $country && $country->is_enabled && $country->is_allowed && $country->vendors_allowed;
        $checks[] = ['label' => 'Country can apply', 'ok' => (bool) $countryOk, 'message' => $countryOk ? "Vendor applications are available in {$country->name}." : 'Your account country is not currently enabled for vendor applications.'];
        $statusOk = (int) $user->status === 1 && !(bool) $user->coin_vendor_flagged;
        $checks[] = ['label' => 'Account standing', 'ok' => $statusOk, 'message' => $statusOk ? 'Your account is in good standing.' : ((bool) $user->coin_vendor_flagged ? 'This account is restricted from vendor applications.' : 'Banned or restricted accounts cannot apply.')];

        $application = CoinVendorApplication::where('user_id', $user->id)->latest('id')->first();
        if ($application && in_array($application->status, ['draft', 'submitted', 'in_review', 'more_information'], true)) {
            $checks[] = ['label' => 'Application status', 'ok' => false, 'message' => 'You already have an application in progress.'];
        } elseif ($vendor = CoinVendor::where('user_id', $user->id)->first()) {
            $checks[] = ['label' => 'Vendor status', 'ok' => false, 'message' => match ($vendor->status) {
                'suspended' => 'Your vendor access is suspended. Contact support or the marketplace team.',
                'revoked' => 'This account is not able to reapply for vendor access.',
                default => 'You already have a vendor profile.',
            }];
        } elseif ($application?->rejected_at && $application->rejected_at->gt(now()->subDays((int) (gs('coin_vendor_rejection_cooldown_days') ?? 30)))) {
            $retry = $application->rejected_at->copy()->addDays((int) (gs('coin_vendor_rejection_cooldown_days') ?? 30));
            $checks[] = ['label' => 'Rejection cooldown', 'ok' => false, 'message' => 'You can apply again after ' . $retry->format('M j, Y') . '.'];
        } else {
            $checks[] = ['label' => 'Application status', 'ok' => true, 'message' => 'You can submit an application.'];
        }

        return ['checks' => $checks, 'eligible' => collect($checks)->every(fn ($check) => $check['ok']), 'application' => $application];
    }

    public function idTypes(): array
    {
        $value = json_decode((string) gs('coin_vendor_id_types'), true);
        return is_array($value) && $value ? array_values(array_filter($value, 'is_string')) : ["Driver's License", "Voter's Card", 'International Passport'];
    }

    public function storePrivate(string $contents, string $extension): string
    {
        $path = 'coin-vendor/' . Str::uuid() . '.' . $extension . '.enc';
        $disk = Storage::build(['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true]);
        $disk->put($path, Crypt::encryptString($contents));
        return $path;
    }

    public function readPrivate(string $path): ?string
    {
        $disk = Storage::build(['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true]);
        if (!$disk->exists($path)) return null;
        return Crypt::decryptString($disk->get($path));
    }

    public function deletePrivate(?string $path): void
    {
        if (!$path) return;
        Storage::build(['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true])->delete($path);
    }

    public function rateBand(CoinVendor $vendor): array
    {
        $currency = $vendor->currency;
        if (!$currency || !$currency->rate_to_usd || (float) $currency->rate_to_usd <= 0) {
            throw new InvalidArgumentException('Choose a currency with a current exchange rate before setting a vendor rate.');
        }
        $rate = CoinRate::where('currency_id', $currency->id)->first();
        if (!$rate || !$rate->buy_price || !$rate->sell_price) {
            throw new InvalidArgumentException('Coin pricing is not active for your currency yet. Contact support.');
        }
        $below = max(0, (float) (gs('coin_vendor_rate_band_below_percent') ?? 3));
        $above = max(0, (float) (gs('coin_vendor_rate_band_above_percent') ?? 3));
        $floor = (float) $rate->sell_price;
        $min = max($floor, (float) $rate->buy_price * (1 - $below / 100));
        $max = (float) $rate->buy_price * (1 + $above / 100);
        return ['min' => $min, 'max' => max($min, $max), 'floor' => $floor, 'buy' => (float) $rate->buy_price];
    }

    public function assertVendorCanTrade(CoinVendor $vendor): void
    {
        if ($vendor->status !== 'active') throw new InvalidArgumentException('This vendor account is not active.');
        $user = $vendor->user;
        if (!$user || (int) $user->status !== 1 || (bool) $user->coin_vendor_flagged) {
            throw new InvalidArgumentException('This vendor account is restricted from new trades.');
        }
        $country = $user->country;
        if (!$country || !$country->is_enabled || !$country->is_allowed || !$country->coin_marketplace_enabled || !$country->vendors_allowed) {
            throw new InvalidArgumentException('Vendor trading is not enabled for this account country.');
        }
    }

    public function cleanupExpiredApplicationData(): int
    {
        $count = 0;
        CoinVendorApplication::whereNotNull('retention_delete_at')->where('retention_delete_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($applications) use (&$count) {
                foreach ($applications as $application) {
                    $this->deletePrivate($application->id_front_path);
                    $this->deletePrivate($application->id_back_path);
                    $this->deletePrivate($application->selfie_video_path);
                    $application->identity_data = null;
                    $application->phone = null;
                    $application->id_number_hash = null;
                    $application->id_last4 = null;
                    $application->device_fingerprint_hash = null;
                    $application->id_front_path = null;
                    $application->id_back_path = null;
                    $application->selfie_video_path = null;
                    $application->challenge_code = null;
                    $application->challenge_token_id = null;
                    $application->challenge_token_hash = null;
                    $application->challenge_user_agent = null;
                    $application->challenge_ip = null;
                    $application->challenge_session_hash = null;
                    $application->challenge_expires_at = null;
                    $application->challenge_used_at = null;
                    $application->retention_delete_at = null;
                    $application->save();
                    \App\Models\CoinVendorAudit::record('application_identity_retention_deleted', $application->id, $application->vendor?->id, null, null, 'Retention period elapsed.');
                    $count++;
                }
            });
        return $count;
    }
}
