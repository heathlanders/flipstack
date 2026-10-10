<?php

namespace App\Jobs\Ebay;

use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\System\BulkOperationJob;
use App\Models\User\User;
use App\Notifications\EbayImportCompleted;
use App\Services\EbayStoreImportService;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportEbayInventory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    protected int $credentialId;

    protected int $organizationId;

    protected int $userId;

    protected int $bulkOperationId;

    protected array $options;

    protected int $startPage;

    protected array $debugLog = [];

    public function __construct(
        int $credentialId,
        int $organizationId,
        int $userId,
        int $bulkOperationId,
        array $options = [],
        int $startPage = 1
    ) {
        $this->credentialId = $credentialId;
        $this->organizationId = $organizationId;
        $this->userId = $userId;
        $this->bulkOperationId = $bulkOperationId;
        $this->options = $options;
        $this->startPage = $startPage;
    }

    public function handle(): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);
        if (! $bulkJob) {
            return;
        }

        $this->addDebugEvent('job_started', [
            'page' => $this->startPage,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // Check for cancellation before starting
        $bulkJob->refresh();
        if ($bulkJob->status === BulkOperationJob::STATUS_CANCELLED) {
            $this->addDebugEvent('cancelled_before_start', ['page' => $this->startPage]);
            $this->saveDebugLog($bulkJob);

            return;
        }

        $credential = IntegrationCredential::find($this->credentialId);
        if (! $credential) {
            $this->addDebugEvent('credential_not_found', ['credential_id' => $this->credentialId]);
            $this->saveDebugLog($bulkJob);
            $bulkJob->markAsFailed('Integration credential not found');

            return;
        }

        if ($this->startPage === 1) {
            $token = $credential->getActiveEbayToken();
            $this->addDebugEvent('credential_check', [
                'has_token' => $token !== null,
                'expired' => $token?->expires_at?->isPast(),
                'needs_refresh' => $token?->needsRefresh(),
                'expires_at' => $token?->expires_at?->toISOString(),
                'environment' => $credential->environment,
            ]);

            $this->addDebugEvent('marked_processing');
            $bulkJob->markAsProcessing();
        }

        $this->saveDebugLog($bulkJob);

        $service = new EbayStoreImportService($credential);
        $service->enableDebug();
        $skipExistingSkus = $this->options['skip_existing_skus'] ?? true;
        $updateExistingProducts = $this->options['update_existing_products'] ?? false;
        $defaultStatus = $this->options['default_status'] ?? 'draft';
        $updateOptions = [
            'preserve_price' => $this->options['preserve_price'] ?? false,
            'preserve_description' => $this->options['preserve_description'] ?? false,
        ];

        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $failed = 0;
        $createdProductIdsThisPage = [];

        try {
            $this->addDebugEvent('page_api_call_start', ['page' => $this->startPage]);
            $this->saveDebugLog($bulkJob);

            $apiStart = microtime(true);
            $result = $service->fetchActiveListings($this->startPage, 200);
            $apiTime = round((microtime(true) - $apiStart) * 1000);

            $totalItems = $result['total_items'];
            $totalPages = $result['total_pages'];

            $this->addDebugEvent('page_api_response', [
                'page' => $this->startPage,
                'api_time_ms' => $apiTime,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'items_in_page' => count($result['items']),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->mergeServiceDebugContext($service);
            $this->saveDebugLog($bulkJob);

            // On first page, store total_items on the bulk job
            if ($this->startPage === 1) {
                $bulkJob->update(['total_items' => $totalItems]);

                // If store is empty, mark completed immediately
                if ($totalItems === 0) {
                    $this->addDebugEvent('empty_store');
                    $this->saveDebugLog($bulkJob);
                    $bulkJob->markAsCompleted();
                    $this->notifyUser($bulkJob);

                    return;
                }
            }

            // Process items on this page
            foreach ($result['items'] as $item) {
                try {
                    $itemId = $item['item_id'];
                    $sku = ! empty($item['sku']) ? $item['sku'] : 'EBAY-'.$itemId;

                    // Check for existing eBay listing
                    if ($service->ebayListingExistsByItemId($this->credentialId, $itemId)) {
                        $service->updateExistingListing($this->credentialId, $itemId, $item);

                        if ($updateExistingProducts) {
                            $existingListing = \App\Models\Ebay\EbayListing::where('integration_credential_id', $this->credentialId)
                                ->where('ebay_listing_id', $itemId)
                                ->first();
                            if ($existingListing && $existingListing->product) {
                                $service->updateProductFromEbayItem($existingListing->product, $item, $this->userId, $updateOptions);
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }

                        $bulkJob->incrementProcessed();

                        continue;
                    }

                    // Check for duplicate SKU
                    if ($service->productExistsBySku($this->organizationId, $sku)) {
                        $service->updateExistingListingByProductSku($this->organizationId, $this->credentialId, $sku, $item);

                        if ($updateExistingProducts) {
                            $existingProduct = \App\Models\Catalog\Product::where('organization_id', $this->organizationId)
                                ->where('sku', $sku)
                                ->first();
                            if ($existingProduct) {
                                $service->updateProductFromEbayItem($existingProduct, $item, $this->userId, $updateOptions);
                                $updated++;
                            } else {
                                $skipped++;
                            }
                        } else {
                            $skipped++;
                        }

                        $bulkJob->incrementProcessed();

                        continue;
                    }

                    // Create new product
                    $product = $service->createProductFromEbayItem(
                        $item,
                        $this->organizationId,
                        $this->userId,
                        $defaultStatus
                    );

                    if ($product) {
                        $service->createEbayListingRecord(
                            $product,
                            $item,
                            $this->credentialId
                        );

                        EbaySyncLog::create([
                            'integration_credential_id' => $this->credentialId,
                            'team_id' => $credential->team_id,
                            'product_id' => $product->id,
                            'operation' => EbaySyncLog::OP_IMPORT_INVENTORY,
                            'direction' => EbaySyncLog::DIRECTION_INBOUND,
                            'ebay_item_id' => $itemId,
                            'ebay_sku' => $sku,
                            'status' => EbaySyncLog::STATUS_SUCCESS,
                            'started_at' => now(),
                            'completed_at' => now(),
                        ]);

                        $createdProductIdsThisPage[] = $product->id;
                        $imported++;
                    } else {
                        $failed++;
                        $bulkJob->incrementFailed(1, [
                            'item_id' => $itemId,
                            'message' => 'Failed to create product',
                            'time' => now()->toISOString(),
                        ]);
                    }

                    $bulkJob->incrementProcessed();

                    $this->mergeServiceDebugContext($service);

                } catch (\Exception $e) {
                    $failed++;
                    $bulkJob->incrementProcessed();
                    $bulkJob->incrementFailed(1, [
                        'item_id' => $item['item_id'] ?? 'unknown',
                        'message' => $e->getMessage(),
                        'time' => now()->toISOString(),
                    ]);

                    Log::warning('eBay import item failed', [
                        'item_id' => $item['item_id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update cumulative counts after each page
            $existingImported = $bulkJob->parameters['imported'] ?? 0;
            $existingSkipped = $bulkJob->parameters['skipped'] ?? 0;
            $existingUpdated = $bulkJob->parameters['updated'] ?? 0;
            $existingCreatedIds = $bulkJob->parameters['created_product_ids'] ?? [];

            $bulkJob->update([
                'parameters' => array_merge($bulkJob->parameters ?? [], [
                    'imported' => $existingImported + $imported,
                    'skipped' => $existingSkipped + $skipped,
                    'updated' => $existingUpdated + $updated,
                    'created_product_ids' => array_values(array_merge($existingCreatedIds, $createdProductIdsThisPage)),
                    'last_completed_page' => $this->startPage,
                    'total_pages' => $totalPages,
                ]),
            ]);

            $this->addDebugEvent('page_completed', [
                'page' => $this->startPage,
                'imported' => $imported,
                'skipped' => $skipped,
                'updated' => $updated,
                'failed' => $failed,
                'cumulative_imported' => $existingImported + $imported,
                'cumulative_skipped' => $existingSkipped + $skipped,
                'cumulative_updated' => $existingUpdated + $updated,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
            $this->saveDebugLog($bulkJob);

            // Determine if there are more pages to process
            $nextPage = $this->startPage + 1;
            if ($nextPage <= $totalPages) {
                // Check for cancellation before dispatching next page
                $bulkJob->refresh();
                if ($bulkJob->status === BulkOperationJob::STATUS_CANCELLED) {
                    $this->addDebugEvent('cancelled_after_page', ['page' => $this->startPage]);
                    $this->saveDebugLog($bulkJob);
                    Log::info('eBay import cancelled', ['bulk_job_id' => $this->bulkOperationId, 'page' => $this->startPage]);
                    $this->notifyUser($bulkJob);

                    return;
                }

                // Dispatch next page job with a small delay for rate limiting
                self::dispatch(
                    $this->credentialId,
                    $this->organizationId,
                    $this->userId,
                    $this->bulkOperationId,
                    $this->options,
                    $nextPage
                )->delay(now()->addMilliseconds(500));

                $this->addDebugEvent('next_page_dispatched', ['next_page' => $nextPage]);
                $this->saveDebugLog($bulkJob);
            } else {
                // All pages processed - mark as completed
                // Final counts are already stored cumulatively after each page
                $this->addDebugEvent('all_pages_completed', [
                    'total_pages' => $totalPages,
                    'final_imported' => $bulkJob->parameters['imported'] ?? 0,
                    'final_skipped' => $bulkJob->parameters['skipped'] ?? 0,
                    'final_updated' => $bulkJob->parameters['updated'] ?? 0,
                ]);

                $this->saveDebugLog($bulkJob);
                $bulkJob->markAsCompleted();
                $this->dispatchLabelingWorkOrder($bulkJob);
                $this->notifyUser($bulkJob);

                Log::info('eBay import completed', [
                    'bulk_job_id' => $this->bulkOperationId,
                    'total_pages' => $totalPages,
                ]);
            }

        } catch (\Exception $e) {
            $this->addDebugEvent('exception', [
                'page' => $this->startPage,
                'message' => $e->getMessage(),
                'location' => $e->getFile().':'.$e->getLine(),
                'trace_preview' => substr($e->getTraceAsString(), 0, 1000),
            ]);
            $this->saveDebugLog($bulkJob);

            Log::error('eBay import job failed', [
                'bulk_job_id' => $this->bulkOperationId,
                'page' => $this->startPage,
                'error' => $e->getMessage(),
            ]);

            $bulkJob->markAsFailed($e->getMessage());
            $this->notifyUser($bulkJob);
        }
    }

    protected function addDebugEvent(string $event, array $data = []): void
    {
        $this->debugLog[] = array_merge([
            'event' => $event,
            'timestamp' => now()->toISOString(),
        ], $data);
    }

    protected function saveDebugLog(BulkOperationJob $bulkJob): void
    {
        $bulkJob->update([
            'parameters' => array_merge($bulkJob->parameters ?? [], [
                'debug_log' => $this->debugLog,
            ]),
        ]);
    }

    protected function mergeServiceDebugContext(EbayStoreImportService $service): void
    {
        $context = $service->getDebugContext();
        if (! empty($context)) {
            foreach ($context as $entry) {
                $this->debugLog[] = $entry;
            }
            $service->clearDebugContext();
        }
    }

    protected function notifyUser(BulkOperationJob $bulkJob): void
    {
        $user = User::find($this->userId);
        if ($user) {
            $user->notify(new EbayImportCompleted($bulkJob));
        }
    }

    protected function dispatchLabelingWorkOrder(BulkOperationJob $bulkJob): void
    {
        $createdIds = array_values(array_unique($bulkJob->parameters['created_product_ids'] ?? []));

        if (empty($createdIds)) {
            return;
        }

        try {
            app(LabelingWorkflowService::class)->createFromImportJob(
                $bulkJob,
                $createdIds,
                $bulkJob->parameters['location_id'] ?? null,
                LabelingWorkOrder::SOURCE_EBAY_IMPORT,
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to create labeling work order from eBay import', [
                'bulk_job_id' => $bulkJob->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);
        if ($bulkJob) {
            $bulkJob->markAsFailed($exception?->getMessage() ?? 'Unknown error');
        }

        Log::error('eBay import job failed permanently', [
            'bulk_job_id' => $this->bulkOperationId,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'ebay',
            'import-inventory',
            'organization:'.$this->organizationId,
        ];
    }
}
