<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\BulkOperationProgress;
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
use Illuminate\Support\Str;

class BulkLocationTransfer implements ShouldQueue
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
            Log::error("BulkLocationTransfer: Job {$this->jobId} not found");

            return;
        }

        $fromLocationId = $this->data['from_location_id'] ?? null;
        $toLocationId = $this->data['to_location_id'] ?? null;
        $transferQuantity = $this->data['quantity'] ?? null;
        $reason = $this->data['reason'] ?? 'Bulk location transfer';

        if (! $fromLocationId || ! $toLocationId) {
            $job->markAsFailed('Source and destination required');

            return;
        }
        if ($fromLocationId === $toLocationId) {
            $job->markAsFailed('Locations must be different');

            return;
        }

        $fromLocation = InventoryLocation::where('id', $fromLocationId)->where('organization_id', $this->organizationId)->where('is_active', true)->first();
        $toLocation = InventoryLocation::where('id', $toLocationId)->where('organization_id', $this->organizationId)->where('is_active', true)->first();

        if (! $fromLocation) {
            $job->markAsFailed('Source location not found');

            return;
        }
        if (! $toLocation) {
            $job->markAsFailed('Destination location not found');

            return;
        }

        $job->markAsProcessing();
        $transferRef = 'TRF-'.strtoupper(Str::random(8)).'-'.$this->jobId;
        $batchSize = $job->parameters['batch_size'] ?? 100;
        $chunks = array_chunk($this->productIds, $batchSize);
        $processed = 0;
        $failed = 0;
        $errors = [];
        $totalTransferred = 0;

        foreach ($chunks as $chunk) {
            if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                $job->markAsCancelled();

                return;
            }

            DB::transaction(function () use ($chunk, $fromLocationId, $toLocationId, $transferQuantity, $reason, $transferRef, &$processed, &$failed, &$errors, &$totalTransferred) {
                foreach ($chunk as $productId) {
                    try {
                        $transferred = $this->transferProductStock($productId, $fromLocationId, $toLocationId, $transferQuantity, $reason, $transferRef);
                        $processed++;
                        $totalTransferred += $transferred;
                        if ($transferred === 0 && count($errors) < 100) {
                            $errors[] = ['product_id' => $productId, 'message' => 'No stock at source', 'type' => 'warning'];
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        if (count($errors) < 100) {
                            $errors[] = ['product_id' => $productId, 'message' => $e->getMessage(), 'type' => 'error'];
                        }
                    }
                }
            });

            $job->update(['processed_items' => $processed, 'failed_items' => $failed, 'errors' => $errors]);
            event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed, $job->total_items, $failed));
            usleep(50000);
        }

        $finalStatus = $failed === $job->total_items ? BulkOperationJob::STATUS_FAILED : BulkOperationJob::STATUS_COMPLETED;
        $job->update(['status' => $finalStatus, 'completed_at' => now(), 'parameters' => array_merge($job->parameters ?? [], ['total_transferred' => $totalTransferred, 'transfer_ref' => $transferRef])]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, $job->operation_type, $job->total_items, $processed, $failed, $finalStatus));
    }

    protected function transferProductStock(string $productId, string $fromLocationId, string $toLocationId, ?int $requestedQty, string $reason, string $transferRef): int
    {
        $sourceLevel = InventoryLevel::where('organization_id', $this->organizationId)
            ->where('product_id', $productId)->where('location_id', $fromLocationId)
            ->whereNull('product_variation_id')->lockForUpdate()->first();

        if (! $sourceLevel || $sourceLevel->quantity_on_hand <= 0) {
            return 0;
        }

        $availableToTransfer = $sourceLevel->quantity_on_hand - $sourceLevel->quantity_reserved;
        if ($availableToTransfer <= 0) {
            return 0;
        }

        $transferQty = $requestedQty !== null ? min($requestedQty, $availableToTransfer) : $availableToTransfer;
        if ($transferQty <= 0) {
            return 0;
        }

        $sourceBalanceBefore = $sourceLevel->quantity_on_hand;
        $sourceBalanceAfter = $sourceBalanceBefore - $transferQty;

        $sourceLevel->update(['quantity_on_hand' => $sourceBalanceAfter, 'last_movement_at' => now(), 'version' => $sourceLevel->version + 1]);

        // Create TRANSFER_OUT movement
        InventoryMovement::create([
            'organization_id' => $this->organizationId, 'product_id' => $productId, 'product_variation_id' => null,
            'location_id' => $fromLocationId, 'type' => InventoryMovement::TYPE_TRANSFER_OUT, 'quantity' => $transferQty,
            'unit_cost' => $sourceLevel->average_cost ?? 0, 'total_cost' => $transferQty * ($sourceLevel->average_cost ?? 0),
            'reference_type' => 'bulk_transfer', 'reference_id' => $this->jobId, 'reference_number' => $transferRef,
            'reason' => $reason, 'notes' => "Transfer to: {$toLocationId}", 'created_by' => $this->userId,
            'balance_before' => $sourceBalanceBefore, 'balance_after' => $sourceBalanceAfter,
        ]);

        // Get or create destination level
        $destLevel = InventoryLevel::where('organization_id', $this->organizationId)
            ->where('product_id', $productId)->where('location_id', $toLocationId)
            ->whereNull('product_variation_id')->lockForUpdate()->first();

        $destBalanceBefore = $destLevel?->quantity_on_hand ?? 0;
        $destBalanceAfter = $destBalanceBefore + $transferQty;

        if ($destLevel) {
            $totalCostBefore = $destBalanceBefore * ($destLevel->average_cost ?? 0);
            $transferCost = $transferQty * ($sourceLevel->average_cost ?? 0);
            $newAvgCost = $destBalanceAfter > 0 ? ($totalCostBefore + $transferCost) / $destBalanceAfter : $sourceLevel->average_cost ?? 0;
            $destLevel->update(['quantity_on_hand' => $destBalanceAfter, 'average_cost' => round($newAvgCost, 2), 'last_movement_at' => now(), 'version' => $destLevel->version + 1]);
        } else {
            $destLevel = InventoryLevel::create([
                'organization_id' => $this->organizationId, 'product_id' => $productId, 'location_id' => $toLocationId,
                'product_variation_id' => null, 'quantity_on_hand' => $destBalanceAfter, 'quantity_reserved' => 0,
                'reorder_point' => 0, 'reorder_quantity' => 0, 'average_cost' => $sourceLevel->average_cost ?? 0,
                'last_movement_at' => now(), 'version' => 1,
            ]);
        }

        // Create TRANSFER_IN movement
        InventoryMovement::create([
            'organization_id' => $this->organizationId, 'product_id' => $productId, 'product_variation_id' => null,
            'location_id' => $toLocationId, 'type' => InventoryMovement::TYPE_TRANSFER_IN, 'quantity' => $transferQty,
            'unit_cost' => $sourceLevel->average_cost ?? 0, 'total_cost' => $transferQty * ($sourceLevel->average_cost ?? 0),
            'reference_type' => 'bulk_transfer', 'reference_id' => $this->jobId, 'reference_number' => $transferRef,
            'reason' => $reason, 'notes' => "Transfer from: {$fromLocationId}", 'created_by' => $this->userId,
            'balance_before' => $destBalanceBefore, 'balance_after' => $destBalanceAfter,
        ]);

        return $transferQty;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('BulkLocationTransfer failed: '.$e->getMessage());
        BulkOperationJob::find($this->jobId)?->markAsFailed($e->getMessage());
    }
}
