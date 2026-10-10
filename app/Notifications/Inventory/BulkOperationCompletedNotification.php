<?php

namespace App\Notifications\Inventory;

use App\Notifications\BaseNotification;

class BulkOperationCompletedNotification extends BaseNotification
{
    public function __construct(
        protected string $operationType,
        protected int $totalItems,
        protected int $processedItems,
        protected int $failedItems,
        protected string $status,
        protected int $jobId,
    ) {}

    public function getPriority(): string
    {
        if ($this->status === 'failed') {
            return self::PRIORITY_HIGH;
        }

        return $this->failedItems > 0 ? self::PRIORITY_HIGH : self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_INVENTORY;
    }

    public function getTitle(): string
    {
        $label = $this->getOperationLabel();

        if ($this->status === 'failed') {
            return "{$label} Failed";
        }

        return $this->failedItems > 0
            ? "{$label} Completed with Errors"
            : "{$label} Completed";
    }

    public function getMessage(): string
    {
        $label = $this->getOperationLabel();

        if ($this->status === 'failed') {
            return "{$label} failed to complete. {$this->processedItems} of {$this->totalItems} items were processed before failure.";
        }

        $msg = "{$label} finished: {$this->processedItems} of {$this->totalItems} items processed.";

        if ($this->failedItems > 0) {
            $msg .= " {$this->failedItems} failed.";
        }

        return $msg;
    }

    public function getActionUrl(): ?string
    {
        return '/inventory/products';
    }

    public function getActionText(): string
    {
        return 'View Products';
    }

    protected function getData(): array
    {
        return [
            'job_id' => $this->jobId,
            'operation_type' => $this->operationType,
            'total_items' => $this->totalItems,
            'processed_items' => $this->processedItems,
            'failed_items' => $this->failedItems,
        ];
    }

    protected function getOperationLabel(): string
    {
        return match ($this->operationType) {
            'stock_adjustment', 'stock_update' => 'Bulk Stock Adjustment',
            'category_update' => 'Bulk Category Update',
            'product_update' => 'Bulk Product Update',
            'location_transfer' => 'Bulk Location Transfer',
            'import' => 'Product Import',
            'export' => 'Product Export',
            default => 'Bulk Operation',
        };
    }
}
