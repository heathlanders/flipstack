<?php

namespace App\Jobs\Fulfillment;

use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSingleEbayOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        protected IntegrationCredential $credential,
        protected string $ebayOrderId
    ) {
        $this->onQueue('ebay');
    }

    public function handle(): void
    {
        try {
            $syncService = new OrderSyncService($this->credential);
            $order = $syncService->syncSingleOrder($this->ebayOrderId);

            Log::info('Single eBay order synced', [
                'ebay_order_id' => $this->ebayOrderId,
                'order_id' => $order->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync single eBay order', [
                'ebay_order_id' => $this->ebayOrderId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
