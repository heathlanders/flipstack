<?php

namespace App\Services\Analytics;

use App\Models\Analytics\OrderFinancial;
use App\Models\Fulfillment\Order;
use App\Models\Marketplace\MarketplaceFee;
use Illuminate\Support\Facades\Log;

class OrderFinancialCalculator
{
    public function calculate(Order $order): OrderFinancial
    {
        $order->load(['items.product', 'shipments']);

        // Revenue = order total amount
        $revenue = (float) ($order->total_amount ?? 0);

        // COGS = sum of (item qty * product cost_price)
        $cogs = 0;
        foreach ($order->items as $item) {
            $unitCost = (float) ($item->product?->cost_price ?? 0);
            $cogs += $unitCost * $item->quantity;
        }

        // Marketplace fees from fee tracking
        $marketplaceFees = MarketplaceFee::where('order_id', $order->id)->sum('amount');

        // Shipping label cost from shipments
        $shippingLabelCost = $order->shipments->sum('label_cost') + $order->shipments->sum('insurance_cost');

        // Shipping charged to buyer
        $shippingCharged = (float) ($order->shipping_cost ?? 0);

        // Tax collected
        $taxCollected = (float) ($order->tax_amount ?? 0);

        // Net profit = revenue - COGS - fees - shipping cost + shipping charged is already in revenue
        $netProfit = $revenue - $cogs - $marketplaceFees - $shippingLabelCost;

        // Profit margin
        $profitMarginPct = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0;

        $financial = OrderFinancial::updateOrCreate(
            ['order_id' => $order->id],
            [
                'organization_id' => $order->organization_id,
                'revenue' => round($revenue, 2),
                'cost_of_goods' => round($cogs, 2),
                'marketplace_fees' => round($marketplaceFees, 2),
                'shipping_label_cost' => round($shippingLabelCost, 2),
                'shipping_charged' => round($shippingCharged, 2),
                'tax_collected' => round($taxCollected, 2),
                'net_profit' => round($netProfit, 2),
                'profit_margin_pct' => $profitMarginPct,
                'calculated_at' => now(),
            ]
        );

        Log::debug('Order financials calculated', [
            'order_id' => $order->id,
            'revenue' => $revenue,
            'cogs' => $cogs,
            'fees' => $marketplaceFees,
            'net_profit' => $netProfit,
        ]);

        return $financial;
    }
}
