<?php

namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Jobs\Ebay\ProcessEbayNotification;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EbayNotificationController extends Controller
{
    public function __construct(protected EbayConfigService $ebayConfig) {}

    /**
     * App-level ECN verification token for the current env.
     *
     * Falls back to the legacy single-token config if env-specific token
     * isn't set (so existing prod deployments keep working).
     */
    protected function appNotificationToken(): ?string
    {
        $key = $this->ebayConfig->isSandbox()
            ? 'services.ebay.sandbox_app_notification_token'
            : 'services.ebay.production_app_notification_token';

        return config($key) ?: config('services.ebay.app_notification_token');
    }

    /**
     * Respond to eBay ECN challenge verification (GET).
     *
     * Supports per-credential endpoints: /webhooks/ebay/{credential}
     * and legacy endpoint: /webhooks/ebay
     */
    public function challenge(Request $request, ?IntegrationCredential $credential = null): Response
    {
        $challengeCode = $request->query('challenge_code');

        if (! $challengeCode) {
            return response('Missing challenge_code', 400);
        }

        if ($credential) {
            $verificationToken = $credential->ebayNotificationToken;
            $endpointUrl = url('/api/webhooks/ebay/'.$credential->id);
        } else {
            $verificationToken = $this->appNotificationToken();
            $endpointUrl = url('/api/webhooks/ebay');
        }

        if (! $verificationToken) {
            Log::error('EbayNotificationController: no notification token configured', [
                'scope' => $credential ? 'credential:'.$credential->id : 'app',
            ]);

            return response('Notification token not configured', 500);
        }

        $challengeResponse = hash('sha256', $challengeCode.$verificationToken.$endpointUrl);

        return response(
            json_encode(['challengeResponse' => $challengeResponse]),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Handle incoming eBay ECN notification (POST).
     *
     * Supports per-credential endpoints: /webhooks/ebay/{credential}
     * and legacy endpoint: /webhooks/ebay
     */
    public function handle(Request $request, ?IntegrationCredential $credential = null): Response
    {
        $signatureHeader = $request->header('X-EBAY-SIGNATURE');

        if (! $signatureHeader) {
            Log::warning('EbayNotificationController: missing X-EBAY-SIGNATURE header');

            return response('OK', 200);
        }

        $payload = $request->json()->all();
        $rawBody = $request->getContent();
        $topic = $payload['metadata']['topic'] ?? $request->header('X-EBAY-TOPIC') ?? 'unknown';

        if ($credential) {
            $verificationToken = $credential->ebayNotificationToken;
        } else {
            $verificationToken = $this->appNotificationToken();
        }

        if (! $verificationToken) {
            Log::error('EbayNotificationController: no verification token available', [
                'scope' => $credential ? 'credential:'.$credential->id : 'app',
                'topic' => $topic,
            ]);

            return response('OK', 200);
        }

        if (! $this->verifySignature($rawBody, $signatureHeader, $verificationToken)) {
            Log::warning('EbayNotificationController: HMAC verification failed', [
                'topic' => $topic,
                'scope' => $credential ? 'credential:'.$credential->id : 'app',
            ]);

            return response('OK', 200);
        }

        if (! $credential) {
            $ebayUserId = $payload['notification']['data']['userId']
                ?? $payload['metadata']['userId']
                ?? null;
            $credential = $this->findEbayCredential($ebayUserId);
        }

        if (! $credential) {
            Log::warning('EbayNotificationController: no credential resolved for verified notification', [
                'topic' => $topic,
            ]);

            return response('OK', 200);
        }

        ProcessEbayNotification::dispatch($credential, $topic, $payload);

        return response('OK', 200);
    }

    /**
     * Verify the HMAC-SHA256 signature from eBay.
     */
    protected function verifySignature(string $rawBody, string $signatureHeader, string $verificationToken): bool
    {
        try {
            $decoded = base64_decode($signatureHeader, strict: true);

            if ($decoded === false) {
                return false;
            }

            $signatureData = json_decode($decoded, associative: true);
            $signatureMessage = $signatureData['signatureMessage'] ?? null;

            if (! $signatureMessage) {
                return false;
            }

            $expected = base64_encode(hash_hmac('sha256', $rawBody, $verificationToken, binary: true));

            return hash_equals($expected, $signatureMessage);
        } catch (\Throwable $e) {
            Log::warning('EbayNotificationController: signature parse error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Find the eBay credential, optionally matching by seller username.
     */
    protected function findEbayCredential(?string $ebayUserId = null): ?IntegrationCredential
    {
        $query = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true);

        if ($ebayUserId) {
            $query->where('seller_username', $ebayUserId);
        }

        return $query->first();
    }
}
