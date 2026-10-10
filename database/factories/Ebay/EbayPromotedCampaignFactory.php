<?php

namespace Database\Factories\Ebay;

use App\Models\Ebay\EbayPromotedCampaign;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EbayPromotedCampaignFactory extends Factory
{
    protected $model = EbayPromotedCampaign::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'integration_credential_id' => null,
            'ebay_campaign_id' => $this->faker->unique()->numerify('CAMP-########'),
            'name' => $this->faker->words(3, true).' Campaign',
            'status' => EbayPromotedCampaign::STATUS_ACTIVE,
            'campaign_type' => EbayPromotedCampaign::TYPE_STANDARD,
            'budget_daily' => $this->faker->randomFloat(2, 5, 100),
            'budget_total' => $this->faker->randomFloat(2, 100, 5000),
            'start_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'end_date' => $this->faker->optional()->dateTimeBetween('+1 day', '+90 days'),
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => EbayPromotedCampaign::STATUS_ACTIVE,
        ]);
    }

    public function paused(): static
    {
        return $this->state([
            'status' => EbayPromotedCampaign::STATUS_PAUSED,
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => EbayPromotedCampaign::STATUS_ENDED,
            'end_date' => now()->subDay(),
        ]);
    }

    public function standard(): static
    {
        return $this->state([
            'campaign_type' => EbayPromotedCampaign::TYPE_STANDARD,
        ]);
    }

    public function advanced(): static
    {
        return $this->state([
            'campaign_type' => EbayPromotedCampaign::TYPE_ADVANCED,
        ]);
    }
}
