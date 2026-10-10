<?php

namespace App\Events;

use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExtensionResearchReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ProductIntakeQueue $queueItem,
        public array $marketPricing,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("queue-item.{$this->queueItem->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'extension-research.received';
    }

    public function broadcastWith(): array
    {
        return [
            'queue_item_id' => $this->queueItem->id,
            'market_pricing' => $this->marketPricing,
            'received_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
