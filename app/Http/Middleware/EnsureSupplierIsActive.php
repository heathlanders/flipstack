<?php

namespace App\Http\Middleware;

use App\Enums\Supplier\SupplierStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupplierIsActive
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user is a supplier and has an active status.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthorizedResponse($request, 'Authentication required.');
        }

        // Check if user is a supplier
        if (! $user->is_supplier) {
            return $this->unauthorizedResponse($request, 'Access restricted to suppliers only.');
        }

        // Check supplier status
        $status = $user->supplier_status;

        if ($status === SupplierStatus::SUSPENDED) {
            return $this->suspendedResponse($request);
        }

        if ($status === SupplierStatus::INACTIVE) {
            return $this->inactiveResponse($request);
        }

        if ($status === SupplierStatus::PENDING) {
            // Allow access to onboarding routes
            if ($this->isOnboardingRoute($request)) {
                return $next($request);
            }

            return $this->pendingResponse($request);
        }

        // Status is active, allow request
        return $next($request);
    }

    /**
     * Check if the current route is an onboarding route.
     */
    protected function isOnboardingRoute(Request $request): bool
    {
        $currentRoute = $request->route()?->getName();

        if ($currentRoute === null) {
            return false;
        }

        return $currentRoute === 'supplier.onboarding'
            || str_starts_with($currentRoute, 'supplier.onboarding.');
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => $message,
            ], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }

    /**
     * Return response for suspended suppliers.
     */
    protected function suspendedResponse(Request $request): Response
    {
        $message = 'Your supplier account has been suspended. Please contact support for assistance.';

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'supplier_suspended',
                'message' => $message,
                'support_email' => config('mail.from.address'),
            ], 403);
        }

        return redirect()->route('supplier.suspended')->with('error', $message);
    }

    /**
     * Return response for inactive suppliers.
     */
    protected function inactiveResponse(Request $request): Response
    {
        $message = 'Your supplier account is currently inactive. Please contact support to reactivate.';

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'supplier_inactive',
                'message' => $message,
                'support_email' => config('mail.from.address'),
            ], 403);
        }

        return redirect()->route('supplier.inactive')->with('error', $message);
    }

    /**
     * Return response for pending suppliers.
     */
    protected function pendingResponse(Request $request): Response
    {
        $message = 'Please complete your onboarding to access the supplier portal.';

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'supplier_pending',
                'message' => $message,
                'onboarding_url' => route('supplier.onboarding'),
            ], 403);
        }

        return redirect()->route('supplier.onboarding')->with('info', $message);
    }
}
