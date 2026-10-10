<?php

namespace App\Services\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Fulfillment\Order;
use App\Services\Amazon\AmazonBaseService;

/**
 * Amazon Buyer-Seller Messaging + Solicitations.
 *
 * Outbound only. Amazon does NOT expose an inbound message inbox API:
 * buyer messages arrive via email to the seller's notification address,
 * not the API. Sellers must check Seller Central for inbound replies.
 *
 * Allowed outbound actions per order are restricted by Amazon. The set
 * is fetched per-order via getMessagingActionsForOrder().
 */
class AmazonMessageService extends AmazonBaseService
{
    public const ACTION_CONFIRM_CUSTOMIZATION_DETAILS = 'confirmCustomizationDetails';

    public const ACTION_LEGAL_DISCLOSURE = 'legalDisclosure';

    public const ACTION_NEGATIVE_FEEDBACK_REMOVAL = 'negativeFeedbackRemoval';

    public const ACTION_CONFIRM_DELIVERY_DETAILS = 'confirmDeliveryDetails';

    public const ACTION_UNEXPECTED_PROBLEM = 'unexpectedProblem';

    public const ACTION_WARRANTY = 'warranty';

    public const ACTION_AMAZON_MOTORS = 'amazonMotors';

    public const ACTION_PRODUCT_CUSTOMIZATION_INFO = 'productCustomizationInfo';

    /**
     * Get the list of messaging actions Amazon will accept for this order.
     */
    public function getMessagingActionsForOrder(string $amazonOrderId): array
    {
        $response = $this->get("/messaging/v1/orders/{$amazonOrderId}", [
            'marketplaceIds' => $this->marketplaceId,
        ]);

        $links = $response['_links']['actions'] ?? [];

        return collect($links)
            ->map(fn ($link) => $link['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Send a message of a specific allowed action type.
     *
     * @param  string  $action  one of the ACTION_* constants
     * @param  string|null  $body  optional message text (some actions accept it)
     * @param  array  $attachments  optional file attachments
     */
    public function sendMessage(
        string $amazonOrderId,
        string $action,
        ?string $body = null,
        array $attachments = [],
        ?Order $order = null
    ): MarketplaceMessage {
        $payload = [];

        if ($body !== null) {
            $payload['text'] = $body;
        }

        if (! empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        $response = $this->post(
            "/messaging/v1/orders/{$amazonOrderId}/messages/{$action}?marketplaceIds={$this->marketplaceId}",
            $payload
        );

        return $this->logOutbound(
            amazonOrderId: $amazonOrderId,
            action: $action,
            body: $body,
            order: $order,
            response: $response,
        );
    }

    /**
     * Request a product review and seller feedback (the Solicitations API).
     * Distinct from /messaging — Amazon caps to one solicitation per order.
     */
    public function requestReview(string $amazonOrderId, ?Order $order = null): MarketplaceMessage
    {
        $response = $this->post(
            "/solicitations/v1/orders/{$amazonOrderId}/solicitations/productReviewAndSellerFeedback?marketplaceIds={$this->marketplaceId}",
            []
        );

        return $this->logOutbound(
            amazonOrderId: $amazonOrderId,
            action: 'productReviewAndSellerFeedback',
            body: null,
            order: $order,
            response: $response,
            messageType: 'solicitation',
        );
    }

    /**
     * Get available Solicitations actions for an order. Buyers can opt out
     * of solicitations, in which case the action list is empty.
     */
    public function getSolicitationActionsForOrder(string $amazonOrderId): array
    {
        $response = $this->get("/solicitations/v1/orders/{$amazonOrderId}", [
            'marketplaceIds' => $this->marketplaceId,
        ]);

        $links = $response['_links']['actions'] ?? [];

        return collect($links)
            ->map(fn ($link) => $link['name'] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    protected function logOutbound(
        string $amazonOrderId,
        string $action,
        ?string $body,
        ?Order $order,
        array $response,
        string $messageType = 'message',
    ): MarketplaceMessage {
        return MarketplaceMessage::create([
            'organization_id' => $this->credential->organization_id,
            'order_id' => $order?->id,
            'customer_id' => $order?->customer_id,
            'marketplace' => MarketplaceMessage::MARKETPLACE_AMAZON,
            'marketplace_message_id' => 'amazon:'.$amazonOrderId.':'.$action.':'.now()->timestamp,
            'marketplace_thread_id' => $amazonOrderId,
            'message_type' => $messageType,
            'direction' => MarketplaceMessage::DIRECTION_OUTBOUND,
            'subject' => $this->subjectForAction($action),
            'body' => $body ?? '',
            'sender_name' => 'Seller',
            'sender_id' => $this->credential->config['amazon_seller_id'] ?? null,
            'status' => MarketplaceMessage::STATUS_READ,
            'received_at' => now(),
        ]);
    }

    protected function subjectForAction(string $action): string
    {
        return match ($action) {
            self::ACTION_CONFIRM_CUSTOMIZATION_DETAILS => 'Customization details confirmation',
            self::ACTION_LEGAL_DISCLOSURE => 'Legal disclosure',
            self::ACTION_NEGATIVE_FEEDBACK_REMOVAL => 'Negative feedback removal request',
            self::ACTION_CONFIRM_DELIVERY_DETAILS => 'Delivery details confirmation',
            self::ACTION_UNEXPECTED_PROBLEM => 'Unexpected problem',
            self::ACTION_WARRANTY => 'Warranty information',
            self::ACTION_AMAZON_MOTORS => 'Amazon Motors',
            self::ACTION_PRODUCT_CUSTOMIZATION_INFO => 'Product customization info',
            'productReviewAndSellerFeedback' => 'Review request',
            default => ucfirst($action),
        };
    }
}
