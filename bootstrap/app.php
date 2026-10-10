<?php

use App\Http\Middleware\EnsureSupplierIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/shopify.php'));
            Route::middleware('web')->group(base_path('routes/amazon.php'));
            Route::middleware('web')->group(base_path('routes/procurement.php'));
            Route::middleware('web')->group(base_path('routes/customers.php'));
            Route::middleware('web')->group(base_path('routes/pricing.php'));
            Route::middleware('web')->group(base_path('routes/analytics.php'));
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            \App\Http\Middleware\HandleNgrokRequests::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\DemoReadOnly::class,
        ]);

        // Apply rate limiting to the API middleware group
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\DemoReadOnly::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'supplier' => EnsureSupplierIsActive::class,
            'supplier.active' => EnsureSupplierIsActive::class,
            'onboarding' => \App\Http\Middleware\EnsureOnboardingComplete::class,
            'inventory.permission' => \App\Http\Middleware\CheckInventoryPermissions::class,
            'api.permission' => \App\Http\Middleware\EnsureApiPermission::class,
            'api.role' => \App\Http\Middleware\EnsureApiRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::render('Errors/403', [
                    'status' => 403,
                    'message' => $e->getMessage() ?: 'You do not have permission to access this resource.',
                ])->toResponse($request)->setStatusCode(403);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }
        });
    })->create();
