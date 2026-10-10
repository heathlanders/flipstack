<?php

namespace App\Http\Middleware;

use App\Models\System\OnboardingProgress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.*', 'logout', 'password.change', 'password.change.update', 'profile.*')) {
            return $next($request);
        }

        // Supplier users have their own onboarding flow
        if ($user->is_supplier) {
            return $next($request);
        }

        $onboarding = OnboardingProgress::where('user_id', $user->id)->first();

        if (! $onboarding || $onboarding->isInProgress()) {
            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
