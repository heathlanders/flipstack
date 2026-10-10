<?php

namespace Database\Factories\Marketing;

use App\Models\Marketing\EmailCampaign;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailCampaignFactory extends Factory
{
    protected $model = EmailCampaign::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->words(3, true).' Campaign',
            'type' => EmailCampaign::TYPE_POST_PURCHASE_FOLLOWUP,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_DELIVERED,
            'trigger_delay_hours' => $this->faker->randomElement([0, 24, 48, 72, 168]),
            'subject_template' => 'Thank you for your purchase, {{ customer_name }}!',
            'body_template' => 'Hi {{ customer_name }}, we hope you are enjoying your {{ product_name }}. Please leave a review!',
            'is_active' => true,
            'sent_count' => $this->faker->numberBetween(0, 500),
            'open_count' => $this->faker->numberBetween(0, 250),
            'click_count' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function postPurchaseFollowup(): static
    {
        return $this->state([
            'type' => EmailCampaign::TYPE_POST_PURCHASE_FOLLOWUP,
            'trigger_event' => EmailCampaign::TRIGGER_ORDER_DELIVERED,
            'trigger_delay_hours' => 24,
        ]);
    }

    public function reviewRequest(): static
    {
        return $this->state([
            'type' => EmailCampaign::TYPE_REVIEW_REQUEST,
            'trigger_event' => EmailCampaign::TRIGGER_DAYS_AFTER_DELIVERY,
            'trigger_delay_hours' => 168,
            'subject_template' => 'How was your experience with {{ product_name }}?',
        ]);
    }

    public function restockAlert(): static
    {
        return $this->state([
            'type' => EmailCampaign::TYPE_RESTOCK_ALERT,
            'subject_template' => '{{ product_name }} is back in stock!',
        ]);
    }

    public function winBack(): static
    {
        return $this->state([
            'type' => EmailCampaign::TYPE_WIN_BACK,
            'trigger_delay_hours' => 720,
            'subject_template' => 'We miss you, {{ customer_name }}! Here is a special offer.',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withNoStats(): static
    {
        return $this->state([
            'sent_count' => 0,
            'open_count' => 0,
            'click_count' => 0,
        ]);
    }
}
