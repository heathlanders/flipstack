<?php

namespace App\Services\Amazon;

/**
 * Wraps the SP-API Notifications API for managing destinations and
 * subscriptions. The destination (our SNS topic) is registered once per
 * app at the AWS account level. Subscriptions are per-seller-credential
 * and dispatched from the OAuth callback after successful authorization.
 */
class AmazonNotificationsService extends AmazonBaseService
{
    public const NOTIFICATION_TYPES = [
        'ANY_OFFER_CHANGED',
        'B2B_ANY_OFFER_CHANGED',
        'FBA_INVENTORY_AVAILABILITY_CHANGES',
        'FEE_PROMOTION',
        'LISTINGS_ITEM_STATUS_CHANGE',
        'MFN_ORDER_STATUS_CHANGE',
        'ORDER_CHANGE',
        'ORDER_STATUS_CHANGE',
    ];

    /**
     * Register an SNS topic ARN as a destination. Idempotent at the AWS
     * level: re-registering the same ARN returns the existing destinationId.
     *
     * @return string destinationId
     */
    public function createDestination(string $name, string $snsArn): string
    {
        $response = $this->post('/notifications/v1/destinations', [
            'name' => $name,
            'resourceSpecification' => [
                'sns' => ['arn' => $snsArn],
            ],
        ]);

        return $response['payload']['destinationId'] ?? throw new AmazonApiException(
            'createDestination response missing destinationId',
            502
        );
    }

    public function listDestinations(): array
    {
        $response = $this->get('/notifications/v1/destinations', []);

        return $response['payload'] ?? [];
    }

    /**
     * Subscribe this credential to a notification type, routed to the
     * given destinationId. Returns the new subscriptionId. The seller's
     * grantless OAuth token (managed by AmazonBaseService) authorizes the
     * subscription.
     *
     * @return string subscriptionId
     */
    public function createSubscription(string $notificationType, string $destinationId, string $payloadVersion = '1.0'): string
    {
        $response = $this->post("/notifications/v1/subscriptions/{$notificationType}", [
            'payloadVersion' => $payloadVersion,
            'destinationId' => $destinationId,
        ]);

        return $response['payload']['subscriptionId'] ?? throw new AmazonApiException(
            'createSubscription response missing subscriptionId',
            502
        );
    }

    public function listSubscriptions(string $notificationType): array
    {
        $response = $this->get("/notifications/v1/subscriptions/{$notificationType}", []);

        return $response['payload'] ?? [];
    }

    public function deleteSubscription(string $notificationType, string $subscriptionId): void
    {
        $this->delete("/notifications/v1/subscriptions/{$notificationType}/{$subscriptionId}");
    }
}
