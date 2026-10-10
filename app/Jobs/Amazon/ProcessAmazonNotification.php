<?php

namespace App\Jobs\Amazon;

use App\Models\Amazon\AmazonListing;
use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\AmazonOrderSyncService;
use App\Services\Fulfillment\FulfillmentAuditService;
use App\Services\Fulfillment\FulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAmazonNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected IntegrationCredential $credential,
        protected string $topic,
        protected array $payload
    ) {
        $this->onQueue('amazon');
    }

    public function handle(): void
    {
        Log::info('Processing Amazon notification', [
            'topic' => $this->topic,
            'credential_id' => $this->credential->id,
        ]);

        match ($this->topic) {
            'ORDER_CHANGE' => $this->handleOrderChange(),
            'ORDER_STATUS_CHANGE', 'MFN_ORDER_STATUS_CHANGE' => $this->handleOrderStatusChange(),
            'LISTINGS_ITEM_STATUS_CHANGE' => $this->handleListingsItemStatusChange(),
            'ANY_OFFER_CHANGED', 'B2B_ANY_OFFER_CHANGED' => $this->handleAnyOfferChanged(),
            default => Log::info('Unhandled Amazon notification topic', ['topic' => $this->topic]),
        };
    }

    /**
     * Resolve the inner notification payload regardless of wrapper shape.
     * SP-API notifications nest under Payload.{NotificationType}Notification.
     * Some downstream consumers may flatten before dispatch.
     */
    protected function inner(string $key): array
    {
        if (isset($this->payload['Payload'][$key]) && is_array($this->payload['Payload'][$key])) {
            return $this->payload['Payload'][$key];
        }

        return $this->payload;
    }

    protected function handleOrderChange(): void
    {
        $amazonOrderId = $this->payload['AmazonOrderId'] ?? null;

        if (! $amazonOrderId) {
            return;
        }

        try {
            $syncService = new AmazonOrderSyncService($this->credential);

            $existingOrder = Order::where('marketplace_order_id', $amazonOrderId)
                ->where('marketplace', 'amazon')
                ->first();

            if ($existingOrder) {
                $syncService->syncOrders(now()->subMinutes(30));
            } else {
                $syncService->syncOrders(now()->subHours(1));
            }
        } catch (\Exception $e) {
            Log::error('Failed to process Amazon order change notification', [
                'amazon_order_id' => $amazonOrderId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleOrderStatusChange(): void
    {
        $amazonOrderId = $this->payload['AmazonOrderId'] ?? null;
        $newStatus = $this->payload['OrderStatus'] ?? null;

        if (! $amazonOrderId) {
            return;
        }

        $order = Order::where('marketplace_order_id', $amazonOrderId)
            ->where('marketplace', 'amazon')
            ->first();

        if (! $order) {
            return;
        }

        if ($newStatus === 'Canceled' || $newStatus === 'Cancelled') {
            if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED])) {
                return;
            }

            try {
                $fulfillmentService = new FulfillmentService(new FulfillmentAuditService);
                $fulfillmentService->cancelOrder($order, 'Cancelled via Amazon', $order->team?->owner ?? \App\Models\User\User::first());
            } catch (\Exception $e) {
                Log::error('Failed to cancel Amazon order from notification', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function handleListingsItemStatusChange(): void
    {
        $data = $this->inner('ListingsItemStatusChangeNotification');
        $sku = $data['Sku'] ?? null;
        $asin = $data['Asin'] ?? null;
        $status = $data['Status'] ?? [];

        if (! $sku) {
            return;
        }

        $listing = AmazonListing::where('integration_credential_id', $this->credential->id)
            ->where('amazon_sku', $sku)
            ->first();

        if (! $listing) {
            Log::info('LISTINGS_ITEM_STATUS_CHANGE: no matching listing', ['sku' => $sku, 'asin' => $asin]);

            return;
        }

        $statusList = is_array($status) ? $status : [$status];
        $isBuyable = in_array('BUYABLE', $statusList, true);
        $isDiscoverable = in_array('DISCOVERABLE', $statusList, true);
        $isSuppressed = in_array('SUPPRESSED', $statusList, true);

        $newStatus = match (true) {
            $isBuyable && $isDiscoverable => AmazonListing::STATUS_ACTIVE,
            $isSuppressed => AmazonListing::STATUS_INACTIVE,
            default => $listing->status,
        };

        $update = ['amazon_status' => implode(',', $statusList)];

        if ($newStatus !== $listing->status) {
            $update['status'] = $newStatus;
        }

        if ($asin && ! $listing->asin) {
            $update['asin'] = $asin;
        }

        if ($isSuppressed) {
            $update['suppressed_reason'] = $data['IssueLocale'] ?? 'amazon_suppressed';
        }

        $listing->update($update);
    }

    protected function handleAnyOfferChanged(): void
    {
        // Phase 8 wires repricing strategies. For now, log the event for
        // operational visibility so we can confirm subscriptions are live.
        $data = $this->inner('AnyOfferChangedNotification');

        Log::info('Amazon offer change received (repricing not yet wired)', [
            'asin' => $data['OfferChangeTrigger']['ASIN'] ?? null,
            'item_condition' => $data['OfferChangeTrigger']['ItemCondition'] ?? null,
            'time_of_change' => $data['OfferChangeTrigger']['TimeOfOfferChange'] ?? null,
            'credential_id' => $this->credential->id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAmazonNotification: job failed', [
            'topic' => $this->topic,
            'credential_id' => $this->credential->id,
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'amazon-notification',
            'topic:'.$this->topic,
        ];
    }
}
