<?php

namespace Database\Factories\Analytics;

use App\Models\Analytics\OrderFinancial;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFinancialFactory extends Factory
{
    protected $model = OrderFinancial::class;

    public function definition(): array
    {
        $revenue = $this->faker->randomFloat(2, 20, 500);
        $cogs = $revenue * $this->faker->randomFloat(2, 0.3, 0.6);
        $marketplaceFees = $revenue * $this->faker->randomFloat(2, 0.05, 0.15);
        $shippingLabelCost = $this->faker->randomFloat(2, 3, 25);
        $shippingCharged = $this->faker->randomFloat(2, 0, 30);
        $taxCollected = $revenue * $this->faker->randomFloat(2, 0, 0.1);
        $netProfit = $revenue - $cogs - $marketplaceFees - $shippingLabelCost + $shippingCharged;
        $profitMargin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : 0;

        return [
            'organization_id' => Organization::factory(),
            'order_id' => null,
            'revenue' => $revenue,
            'cost_of_goods' => round($cogs, 2),
            'marketplace_fees' => round($marketplaceFees, 2),
            'shipping_label_cost' => $shippingLabelCost,
            'shipping_charged' => $shippingCharged,
            'tax_collected' => round($taxCollected, 2),
            'net_profit' => round($netProfit, 2),
            'profit_margin_pct' => $profitMargin,
            'calculated_at' => now(),
        ];
    }

    public function profitable(): static
    {
        return $this->state(function () {
            $revenue = $this->faker->randomFloat(2, 100, 500);
            $cogs = $revenue * 0.3;
            $fees = $revenue * 0.1;
            $shipping = 8.00;
            $netProfit = $revenue - $cogs - $fees - $shipping;

            return [
                'revenue' => $revenue,
                'cost_of_goods' => round($cogs, 2),
                'marketplace_fees' => round($fees, 2),
                'shipping_label_cost' => $shipping,
                'net_profit' => round($netProfit, 2),
                'profit_margin_pct' => round(($netProfit / $revenue) * 100, 2),
            ];
        });
    }

    public function unprofitable(): static
    {
        return $this->state(function () {
            $revenue = $this->faker->randomFloat(2, 20, 50);
            $cogs = $revenue * 0.7;
            $fees = $revenue * 0.13;
            $shipping = 15.00;
            $netProfit = $revenue - $cogs - $fees - $shipping;

            return [
                'revenue' => $revenue,
                'cost_of_goods' => round($cogs, 2),
                'marketplace_fees' => round($fees, 2),
                'shipping_label_cost' => $shipping,
                'net_profit' => round($netProfit, 2),
                'profit_margin_pct' => round(($netProfit / $revenue) * 100, 2),
            ];
        });
    }
}
