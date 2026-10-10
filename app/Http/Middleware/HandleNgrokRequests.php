<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class HandleNgrokRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply in local environment
        if (Config::get('app.env') !== 'local') {
            return $next($request);
        }

        // Check if request is coming from ngrok
        if ($this->isNgrokRequest($request)) {
            // Force HTTPS for ngrok
            URL::forceScheme('https');

            // Update app URL for this request
            Config::set('app.url', $request->getSchemeAndHttpHost());

            // Update session configuration for ngrok
            Config::set('session.domain', null); // Allow any domain
            Config::set('session.secure', true); // HTTPS required
            Config::set('session.same_site', 'lax'); // Important for cross-site

            // Trust ngrok proxy headers
            $request->setTrustedProxies(['127.0.0.1', '::1'],
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
            );
        }

        return $next($request);
    }

    private function isNgrokRequest(Request $request): bool
    {
        $host = $request->getHost();

        return Str::contains($host, 'ngrok') ||
               Str::endsWith($host, '.ngrok.io') ||
               Str::endsWith($host, '.ngrok-free.app') ||
               Str::endsWith($host, '.ngrok.app');
    }
}
