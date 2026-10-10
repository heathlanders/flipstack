<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects every mutating request when the instance runs in demo mode
 * (APP_DEMO=true), so the public demo stays read-only. Auth routes are
 * exempt so demo visitors can still sign in and out.
 */
class DemoReadOnly
{
    private const ALLOWED_ROUTES = [
        'login.store',
        'logout',
        'two-factor.login.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.demo')) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        $message = 'This demo is read-only — changes are disabled.';

        if ($request->header('X-Inertia')) {
            return Inertia::render('Errors/403', [
                'status' => 403,
                'message' => $message,
            ])->toResponse($request)->setStatusCode(403);
        }

        return response()->json(['message' => $message], 403);
    }
}
