<?php

namespace Database\Factories\Ebay;

use App\Models\Catalog\Product;
use App\Models\Ebay\EbayPromotedCampaign;
use App\Models\Ebay\EbayPromotedListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class EbayPromotedListingFactory extends Factory
{
    protected $model = EbayPromotedListing::class;

    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(100, 10000);
        $clicks = $this->faker->numberBetween(5, (int) ($impressions * 0.1));
        $salesCount = $this->faker->numberBetween(0, (int) ($clicks * 0.3));
        $salesAmount = $salesCount * $this->faker->randomFloat(2, 20, 200);
        $adFees = $salesAmount * $this->faker->randomFloat(2, 0.02, 0.10);
        $roas = $adFees > 0 ? round($salesAmount / $adFees, 2) : 0;

        return [
            'campaign_id' => EbayPromotedCampaign::factory(),
            'ebay_listing_id' => null,
            'product_id' => Product::factory(),
            'ad_rate_percentage' => $this->faker->randomFloat(2, 1, 15),
            'status' => 'active',
            'impressions' => $impressions,
            'clicks' => $clicks,
            'sales_count' => $salesCount,
            'sales_amount' => round($salesAmount, 2),
            'ad_fees' => round($adFees, 2),
            'roas' => $roas,
            'last_synced_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function withNoStats(): static
    {
        return $this->state([
            'impressions' => 0,
            'clicks' => 0,
            'sales_count' => 0,
            'sales_amount' => 0,
            'ad_fees' => 0,
            'roas' => 0,
        ]);
    }
}
