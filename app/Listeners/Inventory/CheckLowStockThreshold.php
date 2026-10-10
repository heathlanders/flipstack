<?php

namespace App\Listeners\Inventory;

use App\Events\Inventory\LowStockAlert;
use App\Events\Inventory\StockUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener that monitors stock updates and triggers alerts
 * when inventory crosses threshold boundaries.
 *
 * This listener:
 * - Detects transitions to out-of-stock state
 * - Detects transitions to low-stock state
 * - Detects transitions to overstocked state
 * - Only triggers alerts on threshold crossings, not every update
 */
class CheckLowStockThreshold implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'notifications';

    /**
     * Handle the event.
     */
    public function handle(StockUpdated $event): void
    {
        $level = $event->inventoryLevel;
        $previousQuantity = $event->previousQuantity;
        $newQuantity = $event->newQuantity;

        // Calculate available quantities
        $previousAvailable = $previousQuantity - $level->quantity_reserved;
        $currentAvailable = $level->quantity_available;

        $reorderPoint = $level->reorder_point;
        $maxStock = $level->max_stock_level;

        // Check for out-of-stock transition
        if ($currentAvailable <= 0 && $previousAvailable > 0) {
            Log::info('Out of stock alert triggered', [
                'product_id' => $event->product->id,
                'product_name' => $event->product->name,
                'location_id' => $level->location_id,
                'previous_available' => $previousAvailable,
                'current_available' => $currentAvailable,
            ]);

            event(new LowStockAlert(
                $event->product,
                $level,
                'out_of_stock',
                $previousQuantity
            ));

            return;
        }

        // Check for low-stock transition
        if ($reorderPoint > 0) {
            $wasAboveThreshold = $previousAvailable > $reorderPoint;
            $isNowBelowThreshold = $currentAvailable <= $reorderPoint && $currentAvailable > 0;

            if ($wasAboveThreshold && $isNowBelowThreshold) {
                Log::info('Low stock alert triggered', [
                    'product_id' => $event->product->id,
                    'product_name' => $event->product->name,
                    'location_id' => $level->location_id,
                    'reorder_point' => $reorderPoint,
                    'current_available' => $currentAvailable,
                ]);

                event(new LowStockAlert(
                    $event->product,
                    $level,
                    'low_stock',
                    $previousQuantity
                ));

                return;
            }
        }

        // Check for overstocked transition
        if ($maxStock) {
            $wasNormal = $previousQuantity <= $maxStock;
            $isNowOverstocked = $newQuantity > $maxStock;

            if ($wasNormal && $isNowOverstocked) {
                Log::info('Overstocked alert triggered', [
                    'product_id' => $event->product->id,
                    'product_name' => $event->product->name,
                    'location_id' => $level->location_id,
                    'max_stock_level' => $maxStock,
                    'current_quantity' => $newQuantity,
                ]);

                event(new LowStockAlert(
                    $event->product,
                    $level,
                    'overstocked',
                    $previousQuantity
                ));
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(StockUpdated $event, \Throwable $exception): void
    {
        Log::error('CheckLowStockThreshold listener failed', [
            'product_id' => $event->product->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
