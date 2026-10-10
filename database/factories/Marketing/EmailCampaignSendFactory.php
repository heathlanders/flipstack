<?php

namespace Database\Factories\Marketing;

use App\Models\Customer\Customer;
use App\Models\Marketing\EmailCampaign;
use App\Models\Marketing\EmailCampaignSend;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailCampaignSendFactory extends Factory
{
    protected $model = EmailCampaignSend::class;

    public function definition(): array
    {
        return [
            'campaign_id' => EmailCampaign::factory(),
            'customer_id' => Customer::factory(),
            'order_id' => null,
            'status' => EmailCampaignSend::STATUS_PENDING,
            'sent_at' => null,
            'opened_at' => null,
            'clicked_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_PENDING,
        ]);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_DELIVERED,
            'sent_at' => now()->subHours(2),
        ]);
    }

    public function opened(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_OPENED,
            'sent_at' => now()->subHours(4),
            'opened_at' => now()->subHours(2),
        ]);
    }

    public function clicked(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_CLICKED,
            'sent_at' => now()->subHours(4),
            'opened_at' => now()->subHours(2),
            'clicked_at' => now()->subHour(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state([
            'status' => EmailCampaignSend::STATUS_BOUNCED,
            'sent_at' => now(),
        ]);
    }
}
