<?php

namespace App\Jobs\Shopify;

use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\System\BulkOperationJob;
use App\Models\User\User;
use App\Notifications\Shopify\ShopifySyncNotification;
use App\Services\Inventory\LabelingWorkflowService;
use App\Services\Shopify\ShopifyStoreImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportShopifyProducts implements ShouldQueue
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

    public function handle(): void
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
            $service = new ShopifyStoreImportService($credential);
            $result = $service->importAllProducts(
                $this->organizationId,
                $this->userId,
                $this->options
            );

            $createdIds = array_values(array_unique($result['created_product_ids'] ?? []));

            $bulkJob->update([
                'total_items' => $result['total'],
                'processed_items' => $result['imported'] + $result['skipped'],
                'failed_items' => $result['failed'],
                'parameters' => array_merge($bulkJob->parameters ?? [], [
                    'imported' => $result['imported'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                    'created_product_ids' => $createdIds,
                ]),
            ]);

            $bulkJob->markAsCompleted();

            $this->dispatchLabelingWorkOrder($bulkJob->fresh(), $createdIds);

            Log::info('Shopify import completed', [
                'bulk_job_id' => $this->bulkOperationId,
                'result' => $result,
            ]);

            $this->notifyUser(new ShopifySyncNotification(
                action: 'import',
                success: true,
                detail: "Shopify import completed: {$result['imported']} imported, {$result['skipped']} skipped, {$result['failed']} failed.",
            ));

        } catch (\Exception $e) {
            Log::error('Shopify import job failed', [
                'bulk_job_id' => $this->bulkOperationId,
                'error' => $e->getMessage(),
            ]);

            $bulkJob->markAsFailed($e->getMessage());

            $this->notifyUser(new ShopifySyncNotification(
                action: 'import',
                success: false,
                detail: 'Shopify import failed: '.$e->getMessage(),
            ));
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);
        $bulkJob?->markAsFailed($exception?->getMessage() ?? 'Unknown error');

        Log::error('Shopify import job failed permanently', [
            'bulk_job_id' => $this->bulkOperationId,
            'error' => $exception?->getMessage(),
        ]);

        $this->notifyUser(new ShopifySyncNotification(
            action: 'import',
            success: false,
            detail: 'Shopify import failed: '.($exception?->getMessage() ?? 'Unknown error'),
        ));
    }

    protected function notifyUser(ShopifySyncNotification $notification): void
    {
        $user = User::find($this->userId);
        $user?->notify($notification);
    }

    protected function dispatchLabelingWorkOrder(BulkOperationJob $bulkJob, array $createdIds): void
    {
        if (empty($createdIds)) {
            return;
        }

        try {
            app(LabelingWorkflowService::class)->createFromImportJob(
                $bulkJob,
                $createdIds,
                $bulkJob->parameters['location_id'] ?? null,
                LabelingWorkOrder::SOURCE_SHOPIFY_IMPORT,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to create labeling work order from Shopify import', [
                'bulk_job_id' => $bulkJob->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return [
            'shopify',
            'import-products',
            'organization:'.$this->organizationId,
        ];
    }
}
