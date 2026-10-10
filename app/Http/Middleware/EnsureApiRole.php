<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\EnforcesMobileAuthz;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a mobile API route by role slug(s) — for operations that have no matching
 * permission slug (fulfillment, returns, labeling, PO receiving, messaging).
 * Passes if the user holds ANY of the listed roles. super_admin and
 * organization_owner always bypass.
 *
 * Usage: ->middleware('api.role:fulfillment_manager,shipper,manager,admin')
 */
class EnsureApiRole
{
    use EnforcesMobileAuthz;

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasRole(['super_admin', 'organization_owner'])) {
            return $next($request);
        }

        if ($user->hasRole($roles)) {
            return $next($request);
        }

        if ($response = $this->denyMobileAuthz($request, $user, 'role', implode(',', $roles))) {
            return $response;
        }

        return $next($request);
    }
}
