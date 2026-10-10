<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\BulkOperationProgress;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\System\BulkOperationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class BulkCategoryUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 3600;

    public function __construct(
        public int $jobId, public array $productIds, public array $data,
        public int $organizationId, public int $userId
    ) {}

    public function handle(): void
    {
        $job = BulkOperationJob::find($this->jobId);
        if (! $job) {
            return;
        }

        $categoryId = $this->data['category_id'] ?? null;
        if ($categoryId && ! Category::where('id', $categoryId)->where('organization_id', $this->organizationId)->exists()) {
            $job->markAsFailed('Invalid category');

            return;
        }

        $job->markAsProcessing();
        $chunks = array_chunk($this->productIds, $job->parameters['batch_size'] ?? 100);
        $processed = 0;
        $failed = 0;

        foreach ($chunks as $chunk) {
            if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                $job->markAsCancelled();

                return;
            }
            $updated = Product::whereIn('id', $chunk)->where('organization_id', $this->organizationId)->update(['category_id' => $categoryId]);
            $processed += $updated;
            $failed += count($chunk) - $updated;
            $job->update(['processed_items' => $processed, 'failed_items' => $failed]);
            event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed, $job->total_items, $failed));
        }

        $status = $failed === $job->total_items ? BulkOperationJob::STATUS_FAILED : BulkOperationJob::STATUS_COMPLETED;
        $job->update(['status' => $status, 'completed_at' => now()]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, $job->operation_type, $job->total_items, $processed, $failed, $status));
    }

    public function failed(\Throwable $e): void
    {
        BulkOperationJob::find($this->jobId)?->markAsFailed($e->getMessage());
    }
}
