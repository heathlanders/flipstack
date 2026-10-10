<?php

namespace App\Http\Middleware;

use App\Models\System\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log the activity after the request is processed
        $this->logActivity($request, $response);

        return $response;
    }

    private function logActivity(Request $request, Response $response): void
    {
        $user = auth()->user();

        if (! $user || $this->shouldSkipLogging($request)) {
            return;
        }

        $organization = app('current_organization') ?? $user->organization;

        AuditLog::create([
            'organization_id' => $organization?->id,
            'user_id' => $user->id,
            'event' => 'page_access',
            'auditable_type' => 'App\Models\User\User',
            'auditable_id' => $user->id,
            'new_values' => [
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function shouldSkipLogging(Request $request): bool
    {
        $skipPatterns = [
            'api/health',
            'api/ping',
            '_ignition',
            'horizon',
            'telescope',
            'livewire',
        ];

        $path = $request->path();

        foreach ($skipPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
