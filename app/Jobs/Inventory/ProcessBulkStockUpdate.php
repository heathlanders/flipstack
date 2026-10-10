<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\LowStockAlert;
use App\Events\Inventory\StockUpdated;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to process bulk stock updates.
 *
 * This job handles large-scale inventory adjustments such as:
 * - Bulk stock adjustments across multiple products
 * - Mass imports from external systems
 * - Stock reconciliation updates
 *
 * Features:
 * - Processes in chunks to avoid memory issues
 * - Creates movement records for audit trail
 * - Dispatches real-time events for each update
 * - Handles failures gracefully with error tracking
 */
class ProcessBulkStockUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The chunk size for processing.
     */
    protected int $chunkSize = 100;

    /**
     * Create a new job instance.
     *
     * @param  array  $updates  Array of updates, each containing:
     *                          - product_id (string/UUID): Product ID
     *                          - location_id (string/UUID): Location ID
     *                          - variation_id (string/UUID|null): Product variation ID
     *                          - adjustment (int): Stock adjustment (+/-)
     *                          - quantity (int): Absolute quantity (for 'set' type)
     *                          - percentage (float): Percentage adjustment (for 'percentage' type)
     * @param  string  $updateType  Type of update: 'adjust', 'set', 'percentage'
     * @param  string  $movementType  Movement type for audit: 'adjustment', 'purchase', etc.
     * @param  int  $organizationId  Organization ID
     * @param  int  $userId  User initiating the update
     * @param  array  $metadata  Additional metadata for the operation
     */
    public function __construct(
        protected array $updates,
        protected string $updateType,
        protected string $movementType,
        protected int $organizationId,
        protected int $userId,
        protected array $metadata = []
    ) {
        $this->onQueue('inventory');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        Log::info('Starting bulk stock update', [
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'update_type' => $this->updateType,
            'total_updates' => count($this->updates),
        ]);

        // Process in chunks
        $chunks = array_chunk($this->updates, $this->chunkSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $index => $update) {
                try {
                    $result = $this->processUpdate($update);

                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'index' => ($chunkIndex * $this->chunkSize) + $index,
                            'product_id' => $update['product_id'] ?? null,
                            'location_id' => $update['location_id'] ?? null,
                            'error' => $result['error'],
                        ];
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    $errors[] = [
                        'index' => ($chunkIndex * $this->chunkSize) + $index,
                        'product_id' => $update['product_id'] ?? null,
                        'location_id' => $update['location_id'] ?? null,
                        'error' => $e->getMessage(),
                    ];

                    Log::warning('Bulk update item failed', [
                        'product_id' => $update['product_id'] ?? null,
                        'location_id' => $update['location_id'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        Log::info('Bulk stock update completed', [
            'organization_id' => $this->organizationId,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'duration_seconds' => $duration,
        ]);

        // Dispatch completion event
        event(new BulkOperationCompleted(
            operationType: 'stock_update',
            organizationId: $this->organizationId,
            userId: $this->userId,
            successCount: $successCount,
            failedCount: $failedCount,
            errors: array_slice($errors, 0, 10), // Limit errors in event
            metadata: array_merge($this->metadata, [
                'update_type' => $this->updateType,
                'movement_type' => $this->movementType,
                'duration_seconds' => $duration,
            ])
        ));
    }

    /**
     * Process a single update.
     */
    protected function processUpdate(array $update): array
    {
        // Validate required fields
        if (empty($update['product_id'])) {
            return ['success' => false, 'error' => 'Missing product_id'];
        }
        if (empty($update['location_id'])) {
            return ['success' => false, 'error' => 'Missing location_id'];
        }

        return DB::transaction(function () use ($update) {
            $productId = $update['product_id'];
            $locationId = $update['location_id'];
            $variationId = $update['variation_id'] ?? null;

            // Get product
            $product = Product::where('organization_id', $this->organizationId)
                ->find($productId);

            if (! $product) {
                return ['success' => false, 'error' => 'Product not found'];
            }

            // Get or create inventory level
            $level = InventoryLevel::firstOrCreate(
                [
                    'organization_id' => $this->organizationId,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'location_id' => $locationId,
                ],
                [
                    'quantity_on_hand' => 0,
                    'quantity_reserved' => 0,
                    'reorder_point' => $update['reorder_point'] ?? 5,
                    'reorder_quantity' => $update['reorder_quantity'] ?? 10,
                    'average_cost' => $update['average_cost'] ?? 0,
                ]
            );

            $previousQuantity = $level->quantity_on_hand;
            $adjustment = $this->calculateAdjustment($update, $previousQuantity);
            $newQuantity = max(0, $previousQuantity + $adjustment);

            // Skip if no change
            if ($newQuantity === $previousQuantity) {
                return ['success' => true, 'error' => null, 'skipped' => true];
            }

            // Create movement record
            InventoryMovement::create([
                'organization_id' => $this->organizationId,
                'product_id' => $productId,
                'product_variation_id' => $variationId,
                'location_id' => $locationId,
                'type' => $this->movementType,
                'quantity' => abs($adjustment),
                'unit_cost' => $update['unit_cost'] ?? null,
                'total_cost' => isset($update['unit_cost']) ? abs($adjustment) * $update['unit_cost'] : null,
                'reference_type' => $this->metadata['reference_type'] ?? 'bulk_update',
                'reference_id' => $this->metadata['reference_id'] ?? null,
                'reference_number' => $this->metadata['reference_number'] ?? 'BULK-'.now()->format('YmdHis'),
                'reason' => $update['reason'] ?? $this->metadata['reason'] ?? 'Bulk stock update',
                'notes' => $update['notes'] ?? null,
                'created_by' => $this->userId,
                'balance_before' => $previousQuantity,
                'balance_after' => $newQuantity,
            ]);

            // Update inventory level
            $level->quantity_on_hand = $newQuantity;
            $level->last_movement_at = now();
            $level->version = $level->version + 1;
            $level->save();

            // Dispatch stock updated event
            event(new StockUpdated(
                $product,
                $level,
                $previousQuantity,
                $newQuantity,
                $this->movementType,
                $this->userId
            ));

            // Check for low stock alert
            $this->checkForStockAlert($product, $level, $previousQuantity);

            // Push updated inventory to marketplaces
            PushInventoryToMarketplaces::dispatch($product->id);

            return ['success' => true, 'error' => null];
        });
    }

    /**
     * Calculate the adjustment based on update type.
     */
    protected function calculateAdjustment(array $update, int $currentQuantity): int
    {
        return match ($this->updateType) {
            'set' => ($update['quantity'] ?? $currentQuantity) - $currentQuantity,
            'percentage' => (int) round($currentQuantity * (($update['percentage'] ?? 0) / 100)),
            'adjust' => $update['adjustment'] ?? 0,
            default => $update['adjustment'] ?? 0,
        };
    }

    /**
     * Check and dispatch stock alert if threshold is crossed.
     */
    protected function checkForStockAlert(Product $product, InventoryLevel $level, int $previousQuantity): void
    {
        $currentAvailable = $level->quantity_available;
        $reorderPoint = $level->reorder_point;
        $maxStock = $level->max_stock_level;
        $previousAvailable = $previousQuantity - $level->quantity_reserved;

        // Check for out of stock transition
        if ($currentAvailable <= 0 && $previousAvailable > 0) {
            event(new LowStockAlert($product, $level, 'out_of_stock', $previousQuantity));

            return;
        }

        // Check for low stock transition
        if ($reorderPoint > 0 && $currentAvailable <= $reorderPoint && $previousAvailable > $reorderPoint) {
            event(new LowStockAlert($product, $level, 'low_stock', $previousQuantity));

            return;
        }

        // Check for overstocked transition
        if ($maxStock && $level->quantity_on_hand > $maxStock && $previousQuantity <= $maxStock) {
            event(new LowStockAlert($product, $level, 'overstocked', $previousQuantity));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk stock update job failed', [
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Dispatch failure event
        event(new BulkOperationCompleted(
            operationType: 'stock_update',
            organizationId: $this->organizationId,
            userId: $this->userId,
            successCount: 0,
            failedCount: count($this->updates),
            errors: [['error' => 'Job failed: '.$exception->getMessage()]],
            metadata: array_merge($this->metadata, [
                'job_failed' => true,
                'exception' => $exception->getMessage(),
            ])
        ));
    }
}
