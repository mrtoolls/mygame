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

        $countryCode = strtoupper((string) $request->header('CF-IPCountry', ''));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            // Production traffic must pass through a trusted GeoIP source (for example
            // Cloudflare). VPNs can still alter the detected location.
            if (app()->environment('local', 'testing')) return $next($request);
            return $this->blocked($request, null, null);
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
                if (Schema::hasTable('country_block_attempts')) {
                    DB::table('country_block_attempts')->insert([
                        'country_id' => null,
                        'user_id' => $user?->id,
                        'ip_address' => $request->ip(),
                        'detected_country_code' => preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : null,
                        'path' => mb_substr($request->path(), 0, 500),
                        'method' => $request->method(),
                        'created_at' => now(),
                    ]);
                }
                return $this->blocked($request, $countryCode, $user?->id, 'Access is restricted.');
            }
        }

        $country = DB::table('countries')->where('iso_code', $countryCode)->first();
        if ($country && $country->is_enabled && $country->is_allowed) {
            if ($user && $user->country_id && Schema::hasTable('country_access_mismatches')) {
                $selectedCode = DB::table('countries')->where('id', $user->country_id)->value('iso_code');
                $alreadyLogged = $selectedCode && $selectedCode !== $countryCode
                    ? DB::table('country_access_mismatches')->where('user_id', $user->id)->where('detected_country_code', $countryCode)->where('created_at', '>=', now()->startOfDay())->exists()
                    : true;
                if ($selectedCode && $selectedCode !== $countryCode && !$alreadyLogged) {
                    DB::table('country_access_mismatches')->insert([
                        'user_id' => $user->id,
                        'ip_address' => $request->ip(),
                        'selected_country_code' => $selectedCode,
                        'detected_country_code' => $countryCode,
                        'created_at' => now(),
                    ]);
                }
            }
            return $next($request);
        }

        return $this->blocked($request, $countryCode, $user?->id, 'Not available in your country.', $country->id ?? null);
    }

    private function blocked(Request $request, ?string $countryCode, ?int $userId, string $message = 'Not available in your country.', ?int $countryId = null): Response
    {
        if (Schema::hasTable('country_block_attempts')) {
            DB::table('country_block_attempts')->insert([
                'country_id' => $countryId,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'detected_country_code' => $countryCode,
                'path' => mb_substr($request->path(), 0, 500),
                'method' => $request->method(),
                'created_at' => now(),
            ]);
        }
        if ($request->expectsJson() || $request->is('api/*')) return response()->json(['message' => $message], 403);
        return response()->view('errors.country-blocked', ['countryCode' => $countryCode], 403);
    }
}
