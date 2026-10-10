<?php

namespace App\Events\Inventory;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class BulkOperationCompleted implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $jobId, public int $organizationId, public int $userId,
        public string $operationType, public int $totalItems, public int $processedItems,
        public int $failedItems, public string $status, public ?string $resultFilePath = null,
        public array $errors = []
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'bulk.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->jobId, 'status' => $this->status,
            'total_items' => $this->totalItems, 'processed_items' => $this->processedItems,
            'failed_items' => $this->failedItems, 'has_download' => ! empty($this->resultFilePath),
        ];
    }
}
