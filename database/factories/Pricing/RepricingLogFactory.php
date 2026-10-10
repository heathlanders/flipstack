<?php

namespace Database\Factories\Pricing;

use App\Models\Catalog\Product;
use App\Models\Pricing\RepricingLog;
use App\Models\Pricing\RepricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepricingLogFactory extends Factory
{
    protected $model = RepricingLog::class;

    public function definition(): array
    {
        $oldPrice = $this->faker->randomFloat(2, 10, 200);
        $competitorPrice = $this->faker->randomFloat(2, 8, 190);

        return [
            'repricing_rule_id' => RepricingRule::factory(),
            'product_id' => Product::factory(),
            'listing_type' => $this->faker->randomElement(['ebay', 'shopify']),
            'listing_id' => $this->faker->numberBetween(100000, 999999),
            'old_price' => $oldPrice,
            'new_price' => $oldPrice + $this->faker->randomFloat(2, -10, 10),
            'competitor_price' => $competitorPrice,
            'reason' => $this->faker->randomElement([
                'Matched lowest competitor price',
                'Beat competitor by fixed amount',
                'Cost plus margin applied',
                'Minimum price floor enforced',
            ]),
            'applied_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
