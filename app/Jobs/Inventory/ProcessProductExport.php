<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\BulkOperationProgress;
use App\Models\Catalog\Product;
use App\Models\System\BulkOperationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessProductExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 3600;

    public function __construct(
        public int $jobId, public array $fields, public string $format,
        public array $filters, public array $productIds,
        public int $organizationId, public int $userId
    ) {}

    public function handle(): void
    {
        $job = BulkOperationJob::find($this->jobId);
        if (! $job) {
            return;
        }
        $job->markAsProcessing();

        $query = Product::where('organization_id', $this->organizationId)->with(['category:id,name', 'brand:id,name']);
        if (! empty($this->productIds)) {
            $query->whereIn('id', $this->productIds);
        }
        if (! empty($this->filters['category_id'])) {
            $query->where('category_id', $this->filters['category_id']);
        }
        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        $total = $query->count();
        $job->update(['total_items' => $total]);

        $filename = 'exports/'.date('Y/m')."/products_{$this->jobId}.{$this->format}";
        $fullPath = storage_path('app/'.$filename);
        @mkdir(dirname($fullPath), 0755, true);

        $handle = fopen($fullPath, 'w');
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $this->fields);

        $processed = 0;
        $query->orderBy('id')->chunk(500, function ($products) use ($handle, $job, &$processed, $total) {
            if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                return false;
            }
            foreach ($products as $product) {
                $row = [];
                foreach ($this->fields as $field) {
                    $row[] = match ($field) {
                        'category.name' => $product->category?->name ?? '',
                        'brand.name' => $product->brand?->name ?? '',
                        'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
                        default => $product->{$field} ?? '',
                    };
                }
                fputcsv($handle, $row);
                $processed++;
            }
            $job->update(['processed_items' => $processed]);
            event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed, $total));

            return true;
        });
        fclose($handle);

        if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
            $job->markAsCancelled();
            @unlink($fullPath);

            return;
        }

        $job->update(['status' => BulkOperationJob::STATUS_COMPLETED, 'completed_at' => now(), 'result_file_path' => $filename]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, 'export', $total, $processed, 0, 'completed', $filename));
    }

    public function failed(\Throwable $e): void
    {
        BulkOperationJob::find($this->jobId)?->markAsFailed($e->getMessage());
    }
}
