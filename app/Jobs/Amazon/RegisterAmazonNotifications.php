<?php

namespace App\Jobs\Amazon;

use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonApiException;
use App\Services\Amazon\AmazonNotificationsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Subscribe a freshly-connected Amazon credential to all SP-API
 * notification types we handle. Dispatched from AmazonOAuthController::
 * callback after the credential token is saved.
 *
 * The destination ARN is read from config('services.amazon.sns_arn').
 * If unset the job skips silently (sandbox / dev) — operators wire SNS
 * once in production AWS, not per-developer.
 */
class RegisterAmazonNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $credentialId) {}

    public function handle(): void
    {
        $credential = IntegrationCredential::find($this->credentialId);

        if (! $credential || ! $credential->isAmazonReady()) {
            Log::info('RegisterAmazonNotifications: credential missing or not ready', [
                'credential_id' => $this->credentialId,
            ]);

            return;
        }

        $snsArn = config('services.amazon.sns_arn');

        if (empty($snsArn)) {
            Log::info('RegisterAmazonNotifications: services.amazon.sns_arn unset, skipping registration', [
                'credential_id' => $this->credentialId,
            ]);

            return;
        }

        $service = new AmazonNotificationsService($credential);

        $destinationId = $this->resolveDestinationId($service, $snsArn);

        if (! $destinationId) {
            Log::error('RegisterAmazonNotifications: could not resolve destinationId', [
                'credential_id' => $credential->id,
                'sns_arn' => $snsArn,
            ]);

            return;
        }

        foreach (AmazonNotificationsService::NOTIFICATION_TYPES as $type) {
            $this->subscribeOrSkip($service, $credential, $type, $destinationId);
        }

        $config = $credential->config ?? [];
        $config['notifications_registered_at'] = now()->toIso8601String();
        $config['notifications_destination_id'] = $destinationId;
        $credential->update(['config' => $config]);
    }

    /**
     * Look up an existing destination by ARN before attempting to create one.
     * Amazon rejects duplicate ARN registrations with 409.
     */
    protected function resolveDestinationId(AmazonNotificationsService $service, string $snsArn): ?string
    {
        try {
            foreach ($service->listDestinations() as $destination) {
                $existingArn = $destination['resource']['sns']['arn']
                    ?? $destination['resourceSpecification']['sns']['arn']
                    ?? null;

                if ($existingArn === $snsArn) {
                    return $destination['destinationId'] ?? null;
                }
            }

            return $service->createDestination(
                'flipstack-'.config('app.env'),
                $snsArn
            );
        } catch (AmazonApiException $e) {
            Log::warning('RegisterAmazonNotifications: destination lookup/create failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function subscribeOrSkip(
        AmazonNotificationsService $service,
        IntegrationCredential $credential,
        string $type,
        string $destinationId
    ): void {
        try {
            $existing = $service->listSubscriptions($type);
            if (! empty($existing) && ($existing['destinationId'] ?? null) === $destinationId) {
                return;
            }

            $service->createSubscription($type, $destinationId);

            Log::info('RegisterAmazonNotifications: subscribed', [
                'credential_id' => $credential->id,
                'type' => $type,
            ]);
        } catch (AmazonApiException $e) {
            // Per-type failures should not block other subscriptions.
            // Some types (B2B_ANY_OFFER_CHANGED) need extra account roles.
            Log::warning('RegisterAmazonNotifications: subscribe failed', [
                'credential_id' => $credential->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RegisterAmazonNotifications failed', [
            'credential_id' => $this->credentialId,
            'error' => $exception->getMessage(),
        ]);
    }
}
