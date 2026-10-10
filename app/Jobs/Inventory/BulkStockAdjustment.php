<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\BulkOperationProgress;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryMovement;
use App\Models\System\BulkOperationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkStockAdjustment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 3600;

    public function __construct(
        public int $jobId,
        public array $productIds,
        public array $data,
        public int $organizationId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $job = BulkOperationJob::find($this->jobId);
        if (! $job) {
            Log::error("BulkStockAdjustment: Job {$this->jobId} not found");

            return;
        }

        $job->markAsProcessing();

        $adjustmentQty = (int) ($this->data['adjustment'] ?? 0);
        $adjustmentType = $this->data['type'] ?? 'add';
        $locationId = $this->data['location_id'] ?? null;
        $reason = $this->data['reason'] ?? 'Bulk stock adjustment';
        $batchSize = $job->parameters['batch_size'] ?? 100;

        // Get default location if not specified
        if (! $locationId) {
            $defaultLocation = InventoryLocation::where('organization_id', $this->organizationId)
                ->where('is_active', true)->orderBy('created_at')->first();
            if (! $defaultLocation) {
                $job->markAsFailed('No active inventory location found');

                return;
            }
            $locationId = $defaultLocation->id;
        }

        // Verify location
        $location = InventoryLocation::where('id', $locationId)
            ->where('organization_id', $this->organizationId)->first();
        if (! $location) {
            $job->markAsFailed('Invalid inventory location');

            return;
        }

        $chunks = array_chunk($this->productIds, $batchSize);
        $processed = 0;
        $failed = 0;
        $errors = [];
        $changedProductIds = [];

        foreach ($chunks as $chunk) {
            if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                $job->markAsCancelled();

                return;
            }

            DB::transaction(function () use ($chunk, $adjustmentQty, $adjustmentType, $locationId, $reason, &$processed, &$failed, &$errors, &$changedProductIds) {
                foreach ($chunk as $productId) {
                    try {
                        if ($this->adjustProductStock($productId, $locationId, $adjustmentQty, $adjustmentType, $reason)) {
                            $changedProductIds[$productId] = true;
                        }
                        $processed++;
                    } catch (\Exception $e) {
                        $failed++;
                        if (count($errors) < 100) {
                            $errors[] = ['product_id' => $productId, 'message' => $e->getMessage()];
                        }
                    }
                }
            });

            $job->update(['processed_items' => $processed, 'failed_items' => $failed, 'errors' => $errors]);
            event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed, $job->total_items, $failed));
            usleep(50000);
        }

        // Push updated quantities to marketplaces once per affected product.
        foreach (array_keys($changedProductIds) as $productId) {
            PushInventoryToMarketplaces::dispatch($productId);
        }

        $finalStatus = $failed === $job->total_items ? BulkOperationJob::STATUS_FAILED : BulkOperationJob::STATUS_COMPLETED;
        $job->update(['status' => $finalStatus, 'completed_at' => now()]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, $job->operation_type, $job->total_items, $processed, $failed, $finalStatus));
    }

    protected function adjustProductStock(string $productId, string $locationId, int $adjustmentQty, string $adjustmentType, string $reason): bool
    {
        $inventoryLevel = InventoryLevel::where('organization_id', $this->organizationId)
            ->where('product_id', $productId)->where('location_id', $locationId)
            ->whereNull('product_variation_id')->lockForUpdate()->first();

        $balanceBefore = $inventoryLevel?->quantity_on_hand ?? 0;
        $newQuantity = match ($adjustmentType) {
            'set' => $adjustmentQty,
            'subtract' => $balanceBefore - $adjustmentQty,
            default => $balanceBefore + $adjustmentQty,
        };
        if ($newQuantity < 0) {
            $newQuantity = 0;
        }

        $quantityChange = abs($newQuantity - $balanceBefore);
        if ($quantityChange === 0) {
            return false;
        }
        $signedDelta = $newQuantity - $balanceBefore;

        if ($inventoryLevel) {
            $inventoryLevel->update(['quantity_on_hand' => $newQuantity, 'last_movement_at' => now(), 'version' => $inventoryLevel->version + 1]);
        } else {
            $inventoryLevel = InventoryLevel::create([
                'organization_id' => $this->organizationId, 'product_id' => $productId, 'location_id' => $locationId,
                'product_variation_id' => null, 'quantity_on_hand' => $newQuantity, 'quantity_reserved' => 0,
                'reorder_point' => 0, 'reorder_quantity' => 0, 'average_cost' => 0, 'last_movement_at' => now(), 'version' => 1,
            ]);
        }

        InventoryMovement::create([
            'organization_id' => $this->organizationId, 'product_id' => $productId, 'product_variation_id' => null,
            'location_id' => $locationId, 'type' => InventoryMovement::TYPE_ADJUSTMENT, 'quantity' => $quantityChange,
            'unit_cost' => $inventoryLevel->average_cost ?? 0, 'total_cost' => $quantityChange * ($inventoryLevel->average_cost ?? 0),
            'reference_type' => 'bulk_operation', 'reference_id' => $this->jobId, 'reference_number' => "BULK-{$this->jobId}",
            'reason' => $reason, 'notes' => "Bulk adjustment: {$adjustmentType} {$adjustmentQty}", 'created_by' => $this->userId,
            'balance_before' => $balanceBefore, 'balance_after' => $newQuantity,
        ]);

        Product::where('id', $productId)->where('organization_id', $this->organizationId)->update(['stock_quantity' => $newQuantity]);

        // Mirror the same delta to available_quantity so marketplace listings
        // reflect manual bulk adjustments. Use the model methods for locking.
        $product = Product::find($productId);
        if ($product) {
            if ($signedDelta > 0) {
                $product->incrementAvailableQuantity($signedDelta);
            } elseif ($signedDelta < 0) {
                $product->decrementAvailableQuantity(abs($signedDelta));
            }
        }

        return true;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('BulkStockAdjustment failed: '.$e->getMessage());
        BulkOperationJob::find($this->jobId)?->markAsFailed($e->getMessage());
    }
}
