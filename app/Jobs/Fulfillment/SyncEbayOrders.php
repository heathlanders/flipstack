<?php

namespace App\Jobs\Fulfillment;

use App\Models\Integration\IntegrationCredential;
use App\Models\User\User;
use App\Notifications\Fulfillment\OrderSyncFailedNotification;
use App\Services\Fulfillment\OrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncEbayOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('ebay');
    }

    public function handle(): void
    {
        $credentials = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->whereNotNull('auto_sync_enabled')
            ->where('auto_sync_enabled', true)
            ->get();

        if ($credentials->isEmpty()) {
            $this->warnAboutDisabledCredentials();

            return;
        }

        foreach ($credentials as $credential) {
            try {
                $syncService = new OrderSyncService($credential);
                $result = $syncService->syncOrders();

                $credential->forceFill(['last_full_sync_at' => now()])->save();

                Log::info('eBay order sync completed', [
                    'credential_id' => $credential->id,
                    'organization_id' => $credential->organization_id,
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'errors' => count($result['errors']),
                ]);
            } catch (\Exception $e) {
                Log::error('eBay order sync failed for credential', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);

                $admins = User::where('organization_id', $credential->organization_id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                    ->get();

                $notification = new OrderSyncFailedNotification('eBay', $e->getMessage());
                foreach ($admins as $admin) {
                    $admin->notify($notification);
                }
            }
        }
    }

    protected function warnAboutDisabledCredentials(): void
    {
        $disabled = IntegrationCredential::where('platform', 'ebay')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('auto_sync_enabled')->orWhere('auto_sync_enabled', false);
            })
            ->get();

        if ($disabled->isEmpty()) {
            return;
        }

        foreach ($disabled as $credential) {
            $cacheKey = "sync-ebay-orders.disabled-warning.{$credential->id}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            Log::warning('eBay auto-sync disabled - orders will not import for this credential', [
                'credential_id' => $credential->id,
                'organization_id' => $credential->organization_id,
                'name' => $credential->name,
            ]);

            $admins = User::where('organization_id', $credential->organization_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'owner']))
                ->get();

            $notification = new OrderSyncFailedNotification(
                'eBay',
                "Auto-sync is disabled for {$credential->name}. New eBay orders will not import. Enable auto-sync in Settings > Integrations."
            );

            foreach ($admins as $admin) {
                $admin->notify($notification);
            }

            Cache::put($cacheKey, true, now()->addDay());
        }
    }
}
