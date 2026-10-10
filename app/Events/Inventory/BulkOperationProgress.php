<?php

namespace App\Events\Inventory;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BulkOperationProgress implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $jobId, public int $organizationId, public int $userId,
        public int $processedItems, public int $totalItems, public int $failedItems = 0
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'bulk.progress';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId,
            'processed_items' => $this->processedItems,
            'total_items' => $this->totalItems,
            'failed_items' => $this->failedItems,
            'progress_percent' => $this->totalItems > 0 ? round(($this->processedItems / $this->totalItems) * 100, 1) : 0,
        ];
    }
}
