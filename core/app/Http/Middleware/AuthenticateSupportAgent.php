<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateSupportAgent
{
    public function handle($request, Closure $next)
    {
        $guard = Auth::guard('support_agent');
        $agent = $guard->user();

        if (!$agent || !$agent->is_active) {
            if ($agent) {
                $guard->logout();
            }
            return redirect()->route('support-agent.login');
        }

        return $next($request);
    }
}
