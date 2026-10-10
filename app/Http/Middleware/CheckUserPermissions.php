<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserPermissions
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $organization = app('current_organization');
        $organizationId = $organization ? $organization->id : $user->organization_id;

        // Super admin bypasses all permission checks
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission, $organizationId)) {
                return $next($request);
            }
        }

        abort(403, 'Insufficient permissions');
    }
}
