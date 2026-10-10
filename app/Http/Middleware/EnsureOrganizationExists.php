<?php

namespace App\Http\Middleware;

use App\Models\Organization\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationExists
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->getSubdomain($request);

        if ($subdomain && $subdomain !== 'www') {
            $organization = Organization::bySubdomain($subdomain)->active()->first();

            if (! $organization) {
                abort(404, 'Organization not found');
            }

            // Store organization in request for later use
            $request->merge(['current_organization' => $organization]);

            // Store in app container for global access
            app()->instance('current_organization', $organization);
        }

        return $next($request);
    }

    private function getSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // If we have at least 3 parts (subdomain.domain.tld), return the first part
        return count($parts) >= 3 ? $parts[0] : null;
    }
}
