<?php

namespace App\Providers;

use App\Models\AI\AiCategoryPrompt;
use App\Models\Catalog\Product;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\Channels\SmsChannel;
use App\Observers\AiCategoryPromptObserver;
use App\Observers\Inventory\ProductObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }

        $this->configureRateLimiting();

        // Register model observers
        AiCategoryPrompt::observe(AiCategoryPromptObserver::class);
        Product::observe(ProductObserver::class);

        // Register custom notification channels (sms, fcm) under string aliases
        // so via() can return ['mail', 'sms', 'fcm'] without FQCNs.
        $this->app->resolving(ChannelManager::class, function (ChannelManager $manager) {
            $manager->extend('sms', fn ($app) => $app->make(SmsChannel::class));
            $manager->extend('fcm', fn ($app) => $app->make(FcmChannel::class));
        });
    }

    protected function configureRateLimiting(): void
    {
        // Default API rate limit: 180 requests/minute per device/user.
        // NOTE: this limiter is prepended to the api group (bootstrap/app.php), so it
        // runs BEFORE auth:sanctum -- $request->user() is null here. Key by the bearer
        // token so each device gets its own bucket instead of all devices behind one
        // IP (cellular CGNAT, office wifi) sharing a single bucket. Fall back to IP for
        // cookie/session requests that carry no bearer token.
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();
            $key = $token ? 'token:'.sha1($token) : 'ip:'.$request->ip();

            return Limit::perMinute(180)->by($key);
        });

        // Stricter limit for public webhook endpoints: 30 requests/minute per IP
        RateLimiter::for('webhooks', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Auth endpoints: 5 attempts/minute to prevent brute force
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
