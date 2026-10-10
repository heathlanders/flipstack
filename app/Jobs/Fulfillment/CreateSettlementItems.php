<?php

namespace App\Jobs\Fulfillment;

use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\OrderItem;
use App\Models\Supplier\SettlementItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateSettlementItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct()
    {
        $this->onQueue('settlements');
    }

    public function handle(): void
    {
        $gracePeriodDays = config('services.easypost.settlement_grace_period_days', 14);

        // Find delivered orders past grace period that haven't had settlements created
        $orders = Order::where('status', Order::STATUS_DELIVERED)
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays($gracePeriodDays))
            ->whereDoesntHave('items', function ($q) {
                $q->whereHas('product', function ($pq) {
                    $pq->whereNotNull('supplier_id');
                });
            })
            ->orWhere(function ($q) use ($gracePeriodDays) {
                $q->where('status', Order::STATUS_DELIVERED)
                    ->whereNotNull('delivered_at')
                    ->where('delivered_at', '<=', now()->subDays($gracePeriodDays));
            })
            ->limit(100)
            ->get();

        $created = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $orderItem) {
                if (! $orderItem->supplier_id) {
                    continue;
                }

                // Check if settlement item already exists for this order item
                $exists = SettlementItem::where('order_item_id', $orderItem->id)->exists();
                if ($exists) {
                    continue;
                }

                try {
                    $this->createSettlementItem($order, $orderItem);
                    $created++;
                } catch (\Exception $e) {
                    Log::error('Failed to create settlement item', [
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($created > 0) {
            Log::info('Settlement items created', ['count' => $created]);
        }
    }

    protected function createSettlementItem(Order $order, OrderItem $orderItem): void
    {
        // Calculate marketplace fee proportional to this item
        $itemRatio = $order->total_amount > 0
            ? $orderItem->total_price / $order->total_amount
            : 0;
        $itemFees = round($order->marketplace_fees * $itemRatio, 2);

        SettlementItem::create([
            'organization_id' => $order->organization_id,
            'supplier_id' => $orderItem->supplier_id,
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'sale_date' => $order->paid_at ?? $order->created_at,
            'sale_reference' => $order->marketplace_order_id,
            'marketplace' => $order->marketplace,
            'sale_price' => $orderItem->unit_price,
            'quantity' => $orderItem->quantity,
            'shipping_collected' => $orderItem->shipping_cost,
            'marketplace_fees' => $itemFees,
            'status' => 'pending',
        ]);
    }
}
