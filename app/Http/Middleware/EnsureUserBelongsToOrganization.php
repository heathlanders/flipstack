<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        $organization = app('current_organization');

        if (! $user) {
            return redirect()->route('login');
        }

        // Super admin can access any organization
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        // Check if user belongs to the current organization
        if ($organization && $user->organization_id !== $organization->id) {
            abort(403, 'You do not have access to this organization');
        }

        return $next($request);
    }
}
