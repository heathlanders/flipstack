<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\Shopify\ProcessShopifyWebhook;
use App\Services\Shopify\ShopifyOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    public function handle(Request $request, ShopifyOAuthService $oauthService)
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $topic = $request->header('X-Shopify-Topic');
        $shopDomain = $request->header('X-Shopify-Shop-Domain');

        if (! $hmac || ! $topic || ! $shopDomain) {
            return response('Missing required headers', 400);
        }

        // Verify HMAC
        $rawBody = $request->getContent();

        if (! $oauthService->verifyWebhook($rawBody, $hmac)) {
            Log::warning('Shopify webhook HMAC verification failed', [
                'shop_domain' => $shopDomain,
                'topic' => $topic,
            ]);

            return response('Invalid HMAC', 401);
        }

        // Dispatch job for async processing and return 200 immediately
        ProcessShopifyWebhook::dispatch(
            $topic,
            $shopDomain,
            $request->json()->all()
        );

        return response('OK', 200);
    }
}
