<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMultiFactorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Skip MFA for certain routes
        $skipRoutes = [
            'two-factor.login',
            'two-factor.challenge',
            'logout',
            'mfa.setup',
            'profile.show',
        ];

        if (in_array($request->route()->getName(), $skipRoutes)) {
            return $next($request);
        }

        // Check if organization requires MFA
        $organization = $user->organization;
        $requiresMfa = $organization?->settings['security']['require_mfa'] ?? false;

        // Check if user has MFA enabled when required
        if ($requiresMfa && ! $user->isMfaEnabled()) {
            return redirect()->route('mfa.setup')
                ->with('warning', 'Multi-factor authentication is required for your organization.');
        }

        // If user has MFA enabled, ensure they've completed the challenge
        if ($user->isMfaEnabled() && ! session('mfa_verified_at')) {
            return redirect()->route('two-factor.challenge');
        }

        // Check if MFA verification has expired (4 hours)
        if (session('mfa_verified_at') && now()->diffInHours(session('mfa_verified_at')) > 4) {
            session()->forget('mfa_verified_at');

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
