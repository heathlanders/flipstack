<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInventoryPermissions
{
    /**
     * Handle an incoming request.
     *
     * Checks that the authenticated user has the required inventory permission
     * within their current organization. Accepts one or more permission names;
     * the user must hold at least one of them to proceed.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // No specific permissions required - just need auth
        if (empty($permissions)) {
            return $next($request);
        }

        $organization = app('current_organization');
        $organizationId = $organization ? $organization->id : $user->organization_id;

        // Super admin bypasses all permission checks
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Organization owner bypasses inventory permission checks
        if ($user->hasRole('organization_owner')) {
            return $next($request);
        }

        // Check if user has any of the required inventory permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission, $organizationId)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this inventory resource.');
    }
}
