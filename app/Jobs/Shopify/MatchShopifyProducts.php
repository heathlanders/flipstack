<?php

namespace App\Jobs\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Models\System\BulkOperationJob;
use App\Services\Marketplace\ProductMatchingService;
use App\Services\Shopify\ShopifyStoreImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MatchShopifyProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        protected int $credentialId,
        protected int $organizationId,
        protected int $userId,
        protected int $bulkOperationId,
        protected array $options = []
    ) {}

    public function handle(ProductMatchingService $matchingService): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);

        if (! $bulkJob) {
            return;
        }

        $credential = IntegrationCredential::find($this->credentialId);

        if (! $credential) {
            $bulkJob->markAsFailed('Integration credential not found');

            return;
        }

        $bulkJob->markAsProcessing();

        try {
            $importService = new ShopifyStoreImportService($credential);
            $shopifyProducts = $importService->fetchAllProducts();

            $bulkJob->update(['total_items' => count($shopifyProducts)]);

            $result = $matchingService->matchShopifyProducts(
                $this->organizationId,
                $this->credentialId,
                $this->bulkOperationId,
                $shopifyProducts
            );

            $bulkJob->update([
                'processed_items' => $result['matched'] + $result['unmatched'] + $result['skipped'],
                'parameters' => array_merge($bulkJob->parameters ?? [], $result),
            ]);

            $bulkJob->markAsCompleted();

            Log::info('Shopify product matching completed', [
                'bulk_job_id' => $this->bulkOperationId,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Shopify product matching job failed', [
                'bulk_job_id' => $this->bulkOperationId,
                'error' => $e->getMessage(),
            ]);

            $bulkJob->markAsFailed($e->getMessage());
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);
        $bulkJob?->markAsFailed($exception?->getMessage() ?? 'Unknown error');

        Log::error('Shopify product matching job failed permanently', [
            'bulk_job_id' => $this->bulkOperationId,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'shopify',
            'match-products',
            'organization:'.$this->organizationId,
        ];
    }
}
