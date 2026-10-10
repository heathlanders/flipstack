<?php

namespace App\Jobs\Fulfillment;

use App\Models\Integration\IntegrationCredential;
use App\Services\Fulfillment\AmazonOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAmazonOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('amazon');
    }

    public function handle(): void
    {
        $credentials = IntegrationCredential::where('platform', 'amazon')
            ->where('is_active', true)
            ->whereNotNull('auto_sync_enabled')
            ->where('auto_sync_enabled', true)
            ->get();

        foreach ($credentials as $credential) {
            try {
                $syncService = new AmazonOrderSyncService($credential);
                $result = $syncService->syncOrders();

                Log::info('Amazon order sync completed', [
                    'credential_id' => $credential->id,
                    'organization_id' => $credential->organization_id,
                    'imported' => $result['imported'],
                    'updated' => $result['updated'],
                    'errors' => count($result['errors']),
                ]);
            } catch (\Exception $e) {
                Log::error('Amazon order sync failed for credential', [
                    'credential_id' => $credential->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
