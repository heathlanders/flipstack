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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkProductUpdate implements ShouldQueue
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
            Log::error("Job {$this->jobId} not found");

            return;
        }

        $job->markAsProcessing();
        $batchSize = $job->parameters['batch_size'] ?? 100;
        $operation = $job->parameters['operation'] ?? 'update_status';
        $chunks = array_chunk($this->productIds, $batchSize);
        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($chunks as $chunk) {
            if (Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                $job->markAsCancelled();

                return;
            }

            DB::transaction(function () use ($chunk, $operation, &$processed, &$failed, &$errors) {
                foreach ($chunk as $productId) {
                    try {
                        $product = Product::where('id', $productId)->where('organization_id', $this->organizationId)->first();
                        if (! $product) {
                            $failed++;
                            $errors[] = ['product_id' => $productId, 'message' => 'Not found'];

                            continue;
                        }
                        $this->applyUpdate($product, $operation);
                        $processed++;
                    } catch (\Exception $e) {
                        $failed++;
                        $errors[] = ['product_id' => $productId, 'message' => $e->getMessage()];
                    }
                }
            });

            $job->update(['processed_items' => $processed, 'failed_items' => $failed, 'errors' => array_slice($errors, -100)]);
            event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed, $job->total_items, $failed));
            usleep(100000);
        }

        $finalStatus = $failed === $job->total_items ? BulkOperationJob::STATUS_FAILED : BulkOperationJob::STATUS_COMPLETED;
        $job->update(['status' => $finalStatus, 'completed_at' => now()]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, $job->operation_type, $job->total_items, $processed, $failed, $finalStatus));
    }

    protected function applyUpdate(Product $product, string $operation): void
    {
        switch ($operation) {
            case 'update_status': $product->update(['status' => $this->data['status']]);
                break;
            case 'update_category': $product->update(['category_id' => $this->data['category_id']]);
                break;
            case 'update_brand': $product->update(['brand_id' => $this->data['brand_id']]);
                break;
            case 'update_prices':
                $price = $this->calculatePrice((float) $product->selling_price);
                $product->update(['selling_price' => $price]);
                break;
            case 'delete': $product->delete();
                break;
        }
    }

    protected function calculatePrice(float $current): float
    {
        $type = $this->data['adjustment_type'] ?? 'fixed';
        $value = (float) ($this->data['adjustment_value'] ?? 0);

        return match ($type) {
            'fixed' => max(0, $value),
            'increase_percent' => round($current * (1 + $value / 100), 2),
            'decrease_percent' => max(0, round($current * (1 - $value / 100), 2)),
            'increase_amount' => round($current + $value, 2),
            'decrease_amount' => max(0, round($current - $value, 2)),
            default => $current,
        };
    }

    public function failed(\Throwable $e): void
    {
        Log::error('BulkProductUpdate failed: '.$e->getMessage());
        $job = BulkOperationJob::find($this->jobId);
        if ($job) {
            $job->markAsFailed($e->getMessage());
        }
    }
}
