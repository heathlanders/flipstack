<?php

namespace App\Notifications;

use App\Models\System\BulkOperationJob;

class EbayImportCompleted extends BaseNotification
{
    protected BulkOperationJob $bulkJob;

    public function __construct(BulkOperationJob $bulkJob)
    {
        $this->bulkJob = $bulkJob;
    }

    public function getPriority(): string
    {
        return $this->bulkJob->status === BulkOperationJob::STATUS_FAILED
            ? self::PRIORITY_HIGH
            : self::PRIORITY_MEDIUM;
    }

    public function getCategory(): string
    {
        return self::CATEGORY_PLATFORM;
    }

    public function getTitle(): string
    {
        return match ($this->bulkJob->status) {
            BulkOperationJob::STATUS_COMPLETED => 'eBay Import Completed',
            BulkOperationJob::STATUS_FAILED => 'eBay Import Failed',
            BulkOperationJob::STATUS_CANCELLED => 'eBay Import Cancelled',
            default => 'eBay Import Finished',
        };
    }

    public function getMessage(): string
    {
        $parameters = $this->bulkJob->parameters ?? [];
        $imported = $parameters['imported'] ?? 0;
        $skipped = $parameters['skipped'] ?? 0;
        $failed = $parameters['failed'] ?? 0;

        return match ($this->bulkJob->status) {
            BulkOperationJob::STATUS_COMPLETED => "eBay import completed: {$imported} imported, {$skipped} skipped, {$failed} failed.",
            BulkOperationJob::STATUS_FAILED => 'eBay import failed: '.($this->bulkJob->errors[0]['message'] ?? 'Unknown error'),
            BulkOperationJob::STATUS_CANCELLED => 'eBay import was cancelled.',
            default => 'eBay import finished with status: '.$this->bulkJob->status,
        };
    }

    public function getActionUrl(): ?string
    {
        return '/marketplace/ebay';
    }

    public function getActionText(): string
    {
        return 'View eBay Listings';
    }

    protected function getData(): array
    {
        $parameters = $this->bulkJob->parameters ?? [];

        return [
            'bulk_job_id' => $this->bulkJob->id,
            'imported' => $parameters['imported'] ?? 0,
            'skipped' => $parameters['skipped'] ?? 0,
            'failed' => $parameters['failed'] ?? 0,
        ];
    }
}
