<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PullEbayTracking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('ebay');
    }

    public function handle(): void
    {
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->where('auto_sync_enabled', true)
            ->get();

        $unshippedStatuses = [
            Order::STATUS_PENDING,
            Order::STATUS_ASSIGNED,
            Order::STATUS_PICKING,
            Order::STATUS_PICKED,
            Order::STATUS_PACKING,
            Order::STATUS_PACKED,
        ];

        foreach ($credentials as $credential) {
            try {
                $service = new OrderSyncService($credential);

                $orders = Order::withoutGlobalScopes()
                    ->where('credential_id', $credential->id)
                    ->where('marketplace', 'ebay')
                    ->whereIn('status', $unshippedStatuses)
                    ->where('updated_at', '>=', now()->subDays(14))
                    ->whereNotNull('marketplace_order_id')
                    ->get();

                foreach ($orders as $order) {
                    $service->pullEbayTracking($order);
                }
            } catch (\Throwable $e) {
                Log::error('Pull eBay tracking job failed for credential', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
