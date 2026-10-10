<?php

namespace App\Http\Controllers\Amazon;

use App\Http\Controllers\Controller;
use App\Jobs\Amazon\ProcessAmazonNotification;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmazonNotificationController extends Controller
{
    public function handle(Request $request): Response
    {
        $messageType = $request->header('x-amz-sns-message-type');

        // Verify SNS signature on all message types
        if (! $this->verifySnsSignature($request)) {
            Log::warning('AmazonNotificationController: invalid SNS signature', [
                'ip' => $request->ip(),
                'message_type' => $messageType,
            ]);

            return response('Forbidden', 403);
        }

        if ($messageType === 'SubscriptionConfirmation') {
            return $this->handleSubscriptionConfirmation($request);
        }

        if ($messageType !== 'Notification') {
            return response('OK', 200);
        }

        $payload = $request->json()->all();
        $messageId = $payload['MessageId'] ?? null;
        $message = json_decode($payload['Message'] ?? '{}', true);
        $topic = $message['NotificationType'] ?? $payload['TopicArn'] ?? 'unknown';

        // Idempotency: SNS may redeliver the same MessageId on transient
        // 4xx/5xx responses. Cache::add atomically writes only if absent.
        if ($messageId && ! Cache::add("amazon:sns:msg:{$messageId}", 1, now()->addHours(24))) {
            Log::info('AmazonNotificationController: duplicate SNS MessageId, skipping', [
                'message_id' => $messageId,
                'topic' => $topic,
            ]);

            return response('OK', 200);
        }

        $sellerId = $this->extractSellerId($message);
        $credential = $this->findAmazonCredential($sellerId);

        if (! $credential) {
            Log::warning('AmazonNotificationController: no Amazon credential matched seller_id', [
                'seller_id' => $sellerId,
                'topic' => $topic,
            ]);

            return response('OK', 200);
        }

        ProcessAmazonNotification::dispatch($credential, $topic, $message);

        return response('OK', 200);
    }

    /**
     * Pull SellerId out of an SP-API notification message body. Every
     * notification type wraps its data under Payload.{Type}Notification
     * with a SellerId field. We sweep all child arrays under Payload
     * since the inner key name varies by notification type.
     */
    protected function extractSellerId(?array $message): ?string
    {
        if (! is_array($message) || empty($message['Payload']) || ! is_array($message['Payload'])) {
            return null;
        }

        foreach ($message['Payload'] as $inner) {
            if (is_array($inner) && ! empty($inner['SellerId'])) {
                return (string) $inner['SellerId'];
            }
        }

        return null;
    }

    protected function handleSubscriptionConfirmation(Request $request): Response
    {
        $payload = $request->json()->all();
        $subscribeUrl = $payload['SubscribeURL'] ?? null;

        if ($subscribeUrl) {
            // Validate the SubscribeURL points to a legitimate AWS SNS domain
            $parsedUrl = parse_url($subscribeUrl);
            $host = $parsedUrl['host'] ?? '';

            if (! preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/i', $host)) {
                Log::warning('Amazon SNS subscription URL has unexpected host', [
                    'host' => $host,
                    'url' => $subscribeUrl,
                ]);

                return response('Forbidden', 403);
            }

            try {
                Http::get($subscribeUrl);
                Log::info('Amazon SNS subscription confirmed', [
                    'topic_arn' => $payload['TopicArn'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to confirm Amazon SNS subscription', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response('OK', 200);
    }

    /**
     * Verify the SNS message signature to ensure it's from AWS.
     *
     * @see https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
     */
    protected function verifySnsSignature(Request $request): bool
    {
        $payload = $request->json()->all();

        // Required fields for signature verification
        $requiredFields = ['SignatureVersion', 'SigningCertURL', 'Signature'];
        foreach ($requiredFields as $field) {
            if (empty($payload[$field])) {
                Log::debug('SNS verification: missing required field', ['field' => $field]);

                return false;
            }
        }

        // Only SignatureVersion 1 is supported
        if ($payload['SignatureVersion'] !== '1') {
            Log::debug('SNS verification: unsupported signature version', [
                'version' => $payload['SignatureVersion'],
            ]);

            return false;
        }

        // Validate the signing cert URL is from AWS SNS
        $certUrl = $payload['SigningCertURL'];
        $parsedCertUrl = parse_url($certUrl);
        $certHost = $parsedCertUrl['host'] ?? '';

        if (! preg_match('/^sns\.[a-z0-9-]+\.amazonaws\.com$/i', $certHost)) {
            Log::warning('SNS verification: signing cert URL has unexpected host', [
                'host' => $certHost,
            ]);

            return false;
        }

        if (($parsedCertUrl['scheme'] ?? '') !== 'https') {
            Log::warning('SNS verification: signing cert URL is not HTTPS');

            return false;
        }

        try {
            // Fetch the signing certificate
            $certResponse = Http::timeout(5)->get($certUrl);

            if (! $certResponse->successful()) {
                Log::warning('SNS verification: failed to fetch signing certificate');

                return false;
            }

            $certificate = openssl_x509_read($certResponse->body());

            if (! $certificate) {
                Log::warning('SNS verification: invalid certificate');

                return false;
            }

            // Build the string to sign based on message type
            $messageType = $request->header('x-amz-sns-message-type');
            $stringToSign = $this->buildStringToSign($payload, $messageType);

            // Verify the signature
            $publicKey = openssl_pkey_get_public($certificate);
            $signature = base64_decode($payload['Signature']);

            $result = openssl_verify($stringToSign, $signature, $publicKey, OPENSSL_ALGO_SHA1);

            return $result === 1;
        } catch (\Exception $e) {
            Log::error('SNS verification: exception during verification', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build the canonical string to sign for SNS signature verification.
     */
    protected function buildStringToSign(array $payload, ?string $messageType): string
    {
        if ($messageType === 'Notification') {
            $fields = ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'];
        } else {
            // SubscriptionConfirmation and UnsubscribeConfirmation
            $fields = ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];
        }

        $stringToSign = '';
        foreach ($fields as $field) {
            if (isset($payload[$field])) {
                $stringToSign .= "{$field}\n{$payload[$field]}\n";
            }
        }

        return $stringToSign;
    }

    protected function findAmazonCredential(?string $sellerId): ?IntegrationCredential
    {
        if (! $sellerId) {
            return null;
        }

        return IntegrationCredential::where('platform', 'amazon')
            ->where('is_active', true)
            ->whereJsonContains('config->amazon_seller_id', $sellerId)
            ->first();
    }
}
