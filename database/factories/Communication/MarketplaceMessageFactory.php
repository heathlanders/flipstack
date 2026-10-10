<?php

namespace Database\Factories\Communication;

use App\Models\Communication\MarketplaceMessage;
use App\Models\Organization\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MarketplaceMessageFactory extends Factory
{
    protected $model = MarketplaceMessage::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => null,
            'order_id' => null,
            'product_id' => null,
            'marketplace' => $this->faker->randomElement([
                MarketplaceMessage::MARKETPLACE_EBAY,
                MarketplaceMessage::MARKETPLACE_SHOPIFY,
            ]),
            'marketplace_message_id' => $this->faker->unique()->numerify('MSG-########'),
            'marketplace_thread_id' => $this->faker->numerify('THREAD-######'),
            'direction' => MarketplaceMessage::DIRECTION_INBOUND,
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraphs(2, true),
            'sender_name' => $this->faker->name(),
            'sender_id' => $this->faker->numerify('BUYER-######'),
            'status' => MarketplaceMessage::STATUS_UNREAD,
            'replied_at' => null,
            'read_at' => null,
            'received_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function unread(): static
    {
        return $this->state([
            'status' => MarketplaceMessage::STATUS_UNREAD,
            'read_at' => null,
        ]);
    }

    public function read(): static
    {
        return $this->state([
            'status' => MarketplaceMessage::STATUS_READ,
            'read_at' => now(),
        ]);
    }

    public function replied(): static
    {
        return $this->state([
            'status' => MarketplaceMessage::STATUS_REPLIED,
            'read_at' => now()->subHours(2),
            'replied_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'status' => MarketplaceMessage::STATUS_ARCHIVED,
            'read_at' => now()->subDays(2),
        ]);
    }

    public function inbound(): static
    {
        return $this->state([
            'direction' => MarketplaceMessage::DIRECTION_INBOUND,
        ]);
    }

    public function outbound(): static
    {
        return $this->state([
            'direction' => MarketplaceMessage::DIRECTION_OUTBOUND,
        ]);
    }

    public function fromEbay(): static
    {
        return $this->state([
            'marketplace' => MarketplaceMessage::MARKETPLACE_EBAY,
        ]);
    }
}
