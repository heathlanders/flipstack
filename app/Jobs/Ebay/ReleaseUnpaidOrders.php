<?php

namespace App\Jobs\Ebay;

use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Models\Catalog\Product;
use App\Models\Fulfillment\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReleaseUnpaidOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(protected int $hoursThreshold = 48) {}

    public function handle(): void
    {
        $cutoff = now()->subHours($this->hoursThreshold);

        $unpaidOrders = Order::where('marketplace', 'ebay')
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('paid_at')
            ->where('created_at', '<', $cutoff)
            ->with('items')
            ->get();

        if ($unpaidOrders->isEmpty()) {
            Log::info('ReleaseUnpaidOrders: no unpaid orders found');

            return;
        }

        $released = 0;

        foreach ($unpaidOrders as $order) {
            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'internal_notes' => trim(($order->internal_notes ?? '')."\n[AUTO-CANCELLED] Unpaid after {$this->hoursThreshold} hours"),
            ]);

            foreach ($order->items as $item) {
                if (! $item->product_id || ! $item->quantity) {
                    continue;
                }

                $product = Product::find($item->product_id);

                if ($product) {
                    $product->incrementAvailableQuantity((int) $item->quantity);

                    PushInventoryToMarketplaces::dispatch($product->id);
                }
            }

            $released++;

            Log::info('ReleaseUnpaidOrders: released inventory for unpaid order', [
                'order_id' => $order->id,
                'marketplace_order_id' => $order->marketplace_order_id,
                'age_hours' => now()->diffInHours($order->created_at),
            ]);
        }

        Log::info('ReleaseUnpaidOrders: completed', [
            'released' => $released,
            'threshold_hours' => $this->hoursThreshold,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ReleaseUnpaidOrders: job failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['ebay', 'unpaid-orders'];
    }
}
