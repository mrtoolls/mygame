<?php

namespace App\Support;

use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class CountryPaymentLimits
{
    public function assertMethodAllowed(User $user, array $identifiers): void
    {
        if (!$this->allowsMethod($user, $identifiers)) {
            throw ValidationException::withMessages(['method_code' => 'This payment method is not available in your country.']);
        }
    }

    public function allowsMethod(User $user, array $identifiers): bool
    {
        if (!Schema::hasTable('countries') || !$user->country_id) return true;
        $country = Country::find($user->country_id);
        $allowed = array_filter(array_map(static fn ($item) => strtoupper(trim((string) $item)), $country?->allowed_payment_methods ?? []));
        if (!$allowed) return true;
        $identifiers = array_map(static fn ($item) => strtoupper(trim((string) $item)), $identifiers);
        return (bool) array_intersect($allowed, $identifiers);
    }

    public function assert(User $user, string $kind, string|int|float $amount): void
    {
        if (!Schema::hasTable('countries') || !$user->country_id) return;
        $country = Country::with('currency')->find($user->country_id);
        if (!$country || !$country->is_enabled || !$country->is_allowed || !$country->currency?->enabled) {
            throw ValidationException::withMessages(['country' => 'Payments are unavailable for the selected country.']);
        }
        $decimals = (int) $country->currency->decimals;
        $minor = app(Money::class)->toMinor((string) $amount, $decimals);
        $value = (int) $minor;
        $minimum = (int) $country->{'min_' . $kind . '_minor'};
        $maximum = (int) $country->{'max_' . $kind . '_minor'};
        if ($minimum > 0 && $value < $minimum) {
            throw ValidationException::withMessages(['amount' => 'The amount is below the minimum allowed for your country.']);
        }
        if ($maximum > 0 && $value > $maximum) {
            throw ValidationException::withMessages(['amount' => 'The amount exceeds the maximum allowed for your country.']);
        }
    }
}
