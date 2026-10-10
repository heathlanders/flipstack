<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\EnforcesMobileAuthz;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a mobile API route by permission slug(s). Passes if the user holds ANY of
 * the listed permissions. super_admin and organization_owner always bypass
 * (mirrors web CheckUserPermissions / CheckInventoryPermissions).
 *
 * Usage: ->middleware('api.permission:inventory.manage_stock')
 *        ->middleware('api.permission:users.create,users.edit')
 */
class EnsureApiPermission
{
    use EnforcesMobileAuthz;

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user->hasRole(['super_admin', 'organization_owner'])) {
            return $next($request);
        }

        $organizationId = $user->organization_id ?? $user->currentTeam?->organization_id;

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission, $organizationId)) {
                return $next($request);
            }
        }

        if ($response = $this->denyMobileAuthz($request, $user, 'permission', implode(',', $permissions))) {
            return $response;
        }

        return $next($request);
    }
}
