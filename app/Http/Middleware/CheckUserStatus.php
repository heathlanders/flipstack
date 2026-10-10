<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Check if user is active
        if (! $user->is_active) {
            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'Your account has been deactivated. Please contact support.');
        }

        // Check if user's organization is active
        if ($user->organization && ! $user->organization->isActive()) {
            auth()->logout();

            return redirect()->route('login')
                ->with('error', 'Your organization account is suspended. Please contact support.');
        }

        // Check if password has expired
        if ($user->isPasswordExpired()) {
            return redirect()->route('password.change')
                ->with('warning', 'Your password has expired. Please update it to continue.');
        }

        return $next($request);
    }
}
