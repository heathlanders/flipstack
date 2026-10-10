<?php

namespace App\Jobs\Fulfillment;

use App\Models\Integration\IntegrationCredential;
use App\Models\User\User;
use App\Notifications\Fulfillment\OrderSyncFailedNotification;
use App\Services\Fulfillment\ShopifyOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncShopifyOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('shopify');
    }

    public function handle(): void
    {
        $credentials = IntegrationCredential::where('platform', 'shopify')
            ->where('is_active', true)
            ->whereNotNull('auto_sync_enabled')
            ->where('auto_sync_enabled', true)
            ->get();

        foreach ($credentials as $credential) {
            try {
                $syncService = new ShopifyOrderSyncService($credential);
                $result = $syncService->syncOrders();

                Log::info('Shopify order sync completed', [
                    'credential_id' => $credential->id,
                    'organization_id' => $credential->organization_id,
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'errors' => count($result['errors']),
                ]);
            } catch (\Exception $e) {
                Log::error('Shopify order sync failed for credential', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);

                $admins = User::where('organization_id', $credential->organization_id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                    ->get();

                $notification = new OrderSyncFailedNotification('Shopify', $e->getMessage());
                foreach ($admins as $admin) {
                    $admin->notify($notification);
                }
            }
        }
    }
}
