<?php

namespace App\Jobs\Ebay;

use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\LabelingWorkOrder;
use App\Models\System\BulkOperationJob;
use App\Models\User\User;
use App\Notifications\EbayImportCompleted;
use App\Services\Ebay\EbayCsvParser;
use App\Services\EbayStoreImportService;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportEbayInventoryFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Re-tries are safe: ebayListingExistsByItemId() + productExistsBySku()
    // make each row's import idempotent. A re-issued chunk just skips
    // already-imported items.
    public int $tries = 2;

    public int $timeout = 180;

    // Keep a chunk's wall-clock well under the redis queue retry_after
    // (default 90s; see config/queue.php). Larger chunks risk redis
    // re-issuing the job to a second worker before the first acks,
    // producing MaxAttemptsExceededException.
    private const CHUNK_SIZE = 50;

    // Cap the debug_log size so a multi-thousand-row CSV doesn't grow the
    // BulkOperationJob.parameters JSON to megabytes. We keep the most
    // recent events, which are what's useful for diagnosing failures.
    private const DEBUG_LOG_MAX = 200;

    protected int $credentialId;

    protected int $organizationId;

    protected int $userId;

    protected int $bulkOperationId;

    protected string $csvPath;

    protected array $options;

    protected int $startRow;

    protected array $debugLog = [];

    public function __construct(
        int $credentialId,
        int $organizationId,
        int $userId,
        int $bulkOperationId,
        string $csvPath,
        array $options = [],
        int $startRow = 0,
    ) {
        $this->credentialId = $credentialId;
        $this->organizationId = $organizationId;
        $this->userId = $userId;
        $this->bulkOperationId = $bulkOperationId;
        $this->csvPath = $csvPath;
        $this->options = $options;
        $this->startRow = $startRow;
    }

    public function handle(): void
    {
        $bulkJob = BulkOperationJob::find($this->bulkOperationId);
        if (! $bulkJob) {
            return;
        }

        $this->addDebugEvent('csv_job_started', [
            'start_row' => $this->startRow,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $bulkJob->refresh();
        if ($bulkJob->status === BulkOperationJob::STATUS_CANCELLED) {
            $this->addDebugEvent('cancelled_before_start', ['start_row' => $this->startRow]);
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

        $absolutePath = Storage::disk('local')->path($this->csvPath);
        if (! is_file($absolutePath)) {
            $this->addDebugEvent('csv_missing', ['path' => $this->csvPath]);
            $this->saveDebugLog($bulkJob);
            $bulkJob->markAsFailed('CSV file missing on disk: '.$this->csvPath);

            return;
        }

        if ($this->startRow === 0) {
            $bulkJob->markAsProcessing();
        }

        $this->saveDebugLog($bulkJob);

        $service = new EbayStoreImportService($credential);
        // Intentionally NOT calling $service->enableDebug() — the CSV job
        // never reads getDebugContext() and the accumulating array just
        // burns memory on large imports.

        $skipExistingSkus = $this->options['skip_existing_skus'] ?? true;
        $defaultStatus = $this->options['default_status'] ?? 'draft';

        $totalRows = (int) ($bulkJob->total_items ?: 0);
        $imported = 0;
        $skipped = 0;
        $failed = 0;
        $processedThisChunk = 0;
        $lastRowIndex = $this->startRow - 1;
        $createdProductIdsThisChunk = [];

        try {
            foreach (EbayCsvParser::streamRows($absolutePath, $this->startRow, self::CHUNK_SIZE) as $rowIndex => $row) {
                $lastRowIndex = $rowIndex;
                $processedThisChunk++;

                try {
                    $item = EbayCsvParser::rowToEbayItem($row);
                    $itemId = $item['item_id'];

                    if ($itemId === '') {
                        $failed++;
                        $bulkJob->incrementProcessed();
                        $bulkJob->incrementFailed(1, [
                            'item_id' => null,
                            'message' => "Row {$rowIndex}: blank Item number",
                            'time' => now()->toISOString(),
                        ]);

                        continue;
                    }

                    $sku = $item['sku'] !== '' ? $item['sku'] : 'EBAY-'.$itemId;
                    $item['sku'] = $sku;

                    if ($service->ebayListingExistsByItemId($this->credentialId, $itemId)) {
                        $skipped++;
                        $bulkJob->incrementProcessed();

                        continue;
                    }

                    if ($skipExistingSkus && $service->productExistsBySku($this->organizationId, $sku)) {
                        $service->updateExistingListingByProductSku($this->organizationId, $this->credentialId, $sku, $item);
                        $skipped++;
                        $bulkJob->incrementProcessed();

                        continue;
                    }

                    $product = $service->createProductFromEbayItem(
                        $item,
                        $this->organizationId,
                        $this->userId,
                        $defaultStatus
                    );

                    if ($product) {
                        $service->createEbayListingRecord($product, $item, $this->credentialId);

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

                        $createdProductIdsThisChunk[] = $product->id;
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
                } catch (\Throwable $e) {
                    $failed++;
                    $bulkJob->incrementProcessed();
                    $bulkJob->incrementFailed(1, [
                        'item_id' => $row[EbayCsvParser::HEADER_ITEM_NUMBER] ?? 'unknown',
                        'message' => $e->getMessage(),
                        'time' => now()->toISOString(),
                    ]);

                    Log::warning('eBay CSV import row failed', [
                        'row_index' => $rowIndex,
                        'item_id' => $row[EbayCsvParser::HEADER_ITEM_NUMBER] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update cumulative counts
            $existingImported = $bulkJob->parameters['imported'] ?? 0;
            $existingSkipped = $bulkJob->parameters['skipped'] ?? 0;
            $existingCreatedIds = $bulkJob->parameters['created_product_ids'] ?? [];

            $nextRow = $lastRowIndex + 1;
            $isFinalChunk = $processedThisChunk < self::CHUNK_SIZE
                || ($totalRows > 0 && $nextRow >= $totalRows);

            $bulkJob->update([
                'parameters' => array_merge($bulkJob->parameters ?? [], [
                    'imported' => $existingImported + $imported,
                    'skipped' => $existingSkipped + $skipped,
                    'updated' => 0,
                    'created_product_ids' => array_values(array_merge($existingCreatedIds, $createdProductIdsThisChunk)),
                    'last_completed_row' => $lastRowIndex,
                ]),
            ]);

            $this->addDebugEvent('chunk_completed', [
                'start_row' => $this->startRow,
                'last_row' => $lastRowIndex,
                'processed_in_chunk' => $processedThisChunk,
                'imported' => $imported,
                'skipped' => $skipped,
                'failed' => $failed,
                'cumulative_imported' => $existingImported + $imported,
                'cumulative_skipped' => $existingSkipped + $skipped,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
            $this->saveDebugLog($bulkJob);

            if ($isFinalChunk) {
                $this->addDebugEvent('all_rows_completed', [
                    'final_imported' => $bulkJob->parameters['imported'] ?? 0,
                    'final_skipped' => $bulkJob->parameters['skipped'] ?? 0,
                    'final_failed' => $bulkJob->failed_items,
                ]);
                $this->saveDebugLog($bulkJob);

                $bulkJob->markAsCompleted();
                $this->cleanupCsv();
                $this->dispatchLabelingWorkOrder($bulkJob);
                $this->notifyUser($bulkJob);

                Log::info('eBay CSV import completed', [
                    'bulk_job_id' => $this->bulkOperationId,
                    'total_processed' => $bulkJob->processed_items,
                ]);

                return;
            }

            // Cancellation guard before dispatching next chunk
            $bulkJob->refresh();
            if ($bulkJob->status === BulkOperationJob::STATUS_CANCELLED) {
                $this->addDebugEvent('cancelled_after_chunk', ['next_row' => $nextRow]);
                $this->saveDebugLog($bulkJob);
                $this->cleanupCsv();
                $this->notifyUser($bulkJob);

                return;
            }

            self::dispatch(
                $this->credentialId,
                $this->organizationId,
                $this->userId,
                $this->bulkOperationId,
                $this->csvPath,
                $this->options,
                $nextRow
            )->delay(now()->addMilliseconds(500));

            $this->addDebugEvent('next_chunk_dispatched', ['next_row' => $nextRow]);
            $this->saveDebugLog($bulkJob);
        } catch (\Throwable $e) {
            $this->addDebugEvent('exception', [
                'start_row' => $this->startRow,
                'message' => $e->getMessage(),
                'location' => $e->getFile().':'.$e->getLine(),
                'trace_preview' => substr($e->getTraceAsString(), 0, 1000),
            ]);
            $this->saveDebugLog($bulkJob);

            Log::error('eBay CSV import job failed', [
                'bulk_job_id' => $this->bulkOperationId,
                'start_row' => $this->startRow,
                'error' => $e->getMessage(),
            ]);

            $bulkJob->markAsFailed($e->getMessage());
            $this->notifyUser($bulkJob);
        }
    }

    protected function cleanupCsv(): void
    {
        try {
            Storage::disk('local')->delete($this->csvPath);
        } catch (\Throwable $e) {
            Log::warning('Failed to delete CSV after import', [
                'path' => $this->csvPath,
                'error' => $e->getMessage(),
            ]);
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
        $existing = $bulkJob->parameters['debug_log'] ?? [];
        $combined = array_merge($existing, $this->debugLog);
        if (count($combined) > self::DEBUG_LOG_MAX) {
            $combined = array_slice($combined, -self::DEBUG_LOG_MAX);
        }

        $bulkJob->update([
            'parameters' => array_merge($bulkJob->parameters ?? [], [
                'debug_log' => $combined,
            ]),
        ]);
        $this->debugLog = [];
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
            Log::warning('Failed to create labeling work order from eBay CSV import', [
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

        Log::error('eBay CSV import job failed permanently', [
            'bulk_job_id' => $this->bulkOperationId,
            'error' => $exception?->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return [
            'ebay',
            'import-inventory-csv',
            'organization:'.$this->organizationId,
        ];
    }
}
