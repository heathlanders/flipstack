<?php

namespace App\Jobs\Ebay;

use App\Jobs\Fulfillment\SyncEbayOrders;
use App\Jobs\Fulfillment\SyncSingleEbayOrder;
use App\Models\Integration\IntegrationCredential;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEbayNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        protected IntegrationCredential $credential,
        protected string $topic,
        protected array $payload
    ) {
        $this->onQueue('ebay');
    }

    public function handle(): void
    {
        Log::info('ProcessEbayNotification: received', [
            'topic' => $this->topic,
            'credential_id' => $this->credential->id,
        ]);

        withAuditActor(null, function () {
            match (true) {
                $this->isOrderTopic() => $this->handleOrderNotification(),
                $this->topic === 'MARKETPLACE_ACCOUNT_DELETION' => $this->handleAccountDeletion(),
                default => Log::info('ProcessEbayNotification: unhandled topic', ['topic' => $this->topic]),
            };
        }, [
            'source' => 'webhook',
            'webhook_topic' => $this->topic,
            'credential_id' => $this->credential->id,
        ], $this->credential->organization);
    }

    protected function isOrderTopic(): bool
    {
        return str_starts_with($this->topic, 'ORDER_')
            || str_starts_with($this->topic, 'ITEM_SOLD')
            || in_array($this->topic, [
                'EBAY_ORDER_CREATED',
                'EBAY_ORDER_UPDATED',
                'AuctionCheckoutComplete',
                'FixedPriceTransaction',
                'ItemMarkedPaid',
            ]);
    }

    protected function handleOrderNotification(): void
    {
        $data = $this->payload['notification']['data'] ?? [];

        $orderId = $data['orderId']
            ?? $data['order']['orderId']
            ?? $data['transaction']['extendedOrderID']
            ?? $data['transaction']['orderId']
            ?? $this->payload['notification']['resource']
            ?? null;

        if ($orderId) {
            SyncSingleEbayOrder::dispatch($this->credential, $orderId);

            Log::info('ProcessEbayNotification: dispatched SyncSingleEbayOrder', [
                'ebay_order_id' => $orderId,
                'topic' => $this->topic,
            ]);

            return;
        }

        Log::warning('ProcessEbayNotification: order notification missing orderId, falling back to full poll', [
            'topic' => $this->topic,
            'payload_keys' => array_keys($this->payload),
        ]);

        SyncEbayOrders::dispatch();
    }

    protected function handleAccountDeletion(): void
    {
        // eBay requires all apps to handle MARKETPLACE_ACCOUNT_DELETION
        // Log and take no further action (user data deletion should be handled per GDPR/eBay policy)
        $userId = $this->payload['notification']['data']['userId']
            ?? $this->payload['notification']['resource']
            ?? 'unknown';

        Log::info('ProcessEbayNotification: MARKETPLACE_ACCOUNT_DELETION received', [
            'ebay_user_id' => $userId,
            'credential_id' => $this->credential->id,
        ]);
    }

    public function tags(): array
    {
        return [
            'ebay-notification',
            'topic:'.$this->topic,
        ];
    }
}
