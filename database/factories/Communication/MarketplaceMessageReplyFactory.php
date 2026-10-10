<?php

namespace Database\Factories\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Communication\MarketplaceMessageReply;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketplaceMessageReplyFactory extends Factory
{
    protected $model = MarketplaceMessageReply::class;

    public function definition(): array
    {
        return [
            'message_id' => MarketplaceMessage::factory(),
            'body' => $this->faker->paragraphs(1, true),
            'sent_by' => User::factory(),
            'sent_at' => now(),
            'marketplace_response_id' => $this->faker->optional()->numerify('REPLY-########'),
            'status' => MarketplaceMessageReply::STATUS_SENT,
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => MarketplaceMessageReply::STATUS_DRAFT,
            'sent_at' => null,
            'marketplace_response_id' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state([
            'status' => MarketplaceMessageReply::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => MarketplaceMessageReply::STATUS_FAILED,
            'sent_at' => now(),
            'marketplace_response_id' => null,
        ]);
    }
}
