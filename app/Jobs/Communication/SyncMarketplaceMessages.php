<?php

namespace App\Jobs\Communication;

use App\Services\Communication\MessageSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncMarketplaceMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    public function __construct(
        protected string $organizationId
    ) {
        $this->onQueue('ebay-sync');
    }

    public function handle(MessageSyncService $syncService): void
    {
        Log::info('SyncMarketplaceMessages: starting sync', [
            'organization_id' => $this->organizationId,
        ]);

        $count = $syncService->syncForOrganization($this->organizationId);

        Log::info('SyncMarketplaceMessages: completed', [
            'organization_id' => $this->organizationId,
            'synced_count' => $count,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['message-sync', 'org:'.$this->organizationId];
    }
}
