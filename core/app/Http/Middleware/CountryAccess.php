<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CountryAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        // Admin access must remain available for recovery if an allowlist is misconfigured.
        if ($request->is('admin', 'admin/*') || $request->routeIs('ipn.*') || !Schema::hasTable('countries')) {
            return $next($request);
        }

        $user = $request->user() ?? auth('sanctum')->user();
        if (Schema::hasTable('country_access_overrides')) {
            $override = DB::table('country_access_overrides')
                ->where(function ($query) use ($user, $request) {
                    $query->where('ip_address', $request->ip());
                    if ($user) $query->orWhere('user_id', $user->id);
                })
                ->where(function ($query) { $query->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->orderByDesc('id')->first();
            if ($override?->is_allowed) return $next($request);
            if ($override && !$override->is_allowed) {
                $countryCode = strtoupper((string) $request->header('CF-IPCountry', ''));
                $detectedCountryCode = preg_match('/^[A-Z]{2}$/', $countryCode) && !in_array($countryCode, ['XX', 'T1'], true) ? $countryCode : null;
                if (Schema::hasTable('country_block_attempts')) {
                    DB::table('country_block_attempts')->insert([
                        'country_id' => null,
                        'user_id' => $user?->id,
                        'ip_address' => $request->ip(),
                        'detected_country_code' => $detectedCountryCode,
                        'path' => mb_substr($request->path(), 0, 500),
                        'method' => $request->method(),
                        'reason' => $detectedCountryCode ? 'Access blocked by an account or IP override.' : 'Unknown country: no usable CF-IPCountry header; no GeoIP database fallback is configured.',
                        'created_at' => now(),
                    ]);
                }
                return $this->blocked($request, $detectedCountryCode, $user?->id, 'Access is restricted.', reason: $detectedCountryCode ? 'Access blocked by an account or IP override.' : 'Unknown country: no usable CF-IPCountry header; no GeoIP database fallback is configured.');
            }
        }

        $countryCode = strtoupper(trim((string) $request->header('CF-IPCountry', '')));
        $detectedCountryCode = preg_match('/^[A-Z]{2}$/', $countryCode) && !in_array($countryCode, ['XX', 'T1'], true) ? $countryCode : null;
        // Cloudflare can send XX when IP geolocation is unavailable. Do not interpret
        // an absent/unknown signal as a blocked country: that was denying all direct
        // cPanel traffic. For signed-in users, use their registration country as a
        // fallback; for guests, registration still limits the selected country to the
        // admin-enabled allowlist. Configure a trusted GeoIP/CDN header for strict
        // pre-authentication country enforcement. VPNs can still alter detected location.
        if (!$detectedCountryCode) {
            if ($user?->country_id) {
                $countryCode = strtoupper((string) DB::table('countries')->where('id', $user->country_id)->value('iso_code'));
            } else {
                return $next($request);
            }
        }

        $country = DB::table('countries')->where('iso_code', $countryCode)->first();
        if ($country && $country->is_enabled && $country->is_allowed) {
            if ($detectedCountryCode && $user && $user->country_id && Schema::hasTable('country_access_mismatches')) {
                $selectedCode = DB::table('countries')->where('id', $user->country_id)->value('iso_code');
                $alreadyLogged = $selectedCode && $selectedCode !== $detectedCountryCode
                    ? DB::table('country_access_mismatches')->where('user_id', $user->id)->where('detected_country_code', $detectedCountryCode)->where('created_at', '>=', now()->startOfDay())->exists()
                    : true;
                if ($selectedCode && $selectedCode !== $detectedCountryCode && !$alreadyLogged) {
                    DB::table('country_access_mismatches')->insert([
                        'user_id' => $user->id,
                        'ip_address' => $request->ip(),
                        'selected_country_code' => $selectedCode,
                        'detected_country_code' => $detectedCountryCode,
                        'created_at' => now(),
                    ]);
                }
            }
            return $next($request);
        }

        return $this->blocked($request, $detectedCountryCode, $user?->id, 'Not available in your country.', $country->id ?? null, $detectedCountryCode ? 'Detected country is blocked or unavailable.' : 'Unknown country: no usable CF-IPCountry header; no GeoIP database fallback is configured, and the signup country is blocked.');
    }

    private function blocked(Request $request, ?string $countryCode, ?int $userId, string $message = 'Not available in your country.', ?int $countryId = null, ?string $reason = null): Response
    {
        if (Schema::hasTable('country_block_attempts')) {
            DB::table('country_block_attempts')->insert([
                'country_id' => $countryId,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'detected_country_code' => $countryCode,
                'path' => mb_substr($request->path(), 0, 500),
                'method' => $request->method(),
                'reason' => $reason ?? ($countryCode ? 'Detected country is blocked or unavailable.' : 'Unknown country: no usable CF-IPCountry header; no GeoIP database fallback is configured.'),
                'created_at' => now(),
            ]);
        }
        if ($request->expectsJson() || $request->is('api/*')) return response()->json(['message' => $message], 403);
        return response()->view('errors.country-blocked', ['countryCode' => $countryCode], 403);
    }
}
