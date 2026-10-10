<?php

namespace App\Jobs\Inventory;

use App\Events\Inventory\BulkOperationCompleted;
use App\Events\Inventory\BulkOperationProgress;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Inventory\InventoryLevel;
use App\Models\System\BulkOperationJob;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 7200;

    public function __construct(
        public int $jobId, public string $filePath, public array $columnMapping,
        public bool $updateExisting, public bool $skipErrors, public array $defaultValues,
        public int $organizationId, public int $userId, public ?string $locationId = null
    ) {}

    public function handle(): void
    {
        $job = BulkOperationJob::find($this->jobId);
        if (! $job) {
            return;
        }

        $fullPath = storage_path('app/'.$this->filePath);
        if (! file_exists($fullPath)) {
            $job->markAsFailed('File not found');

            return;
        }

        $job->markAsProcessing();
        $categories = Category::where('organization_id', $this->organizationId)->pluck('id', 'name')->toArray();
        $brands = Brand::where('organization_id', $this->organizationId)->pluck('id', 'name')->toArray();

        $handle = fopen($fullPath, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }
        $header = fgetcsv($handle);

        $columnIndices = [];
        foreach ($this->columnMapping as $target => $source) {
            $idx = array_search($source, $header);
            if ($idx !== false) {
                $columnIndices[$target] = $idx;
            }
        }

        // Count rows
        $totalRows = 0;
        while (fgetcsv($handle) !== false) {
            $totalRows++;
        }
        rewind($handle);
        fgetcsv($handle);
        $job->update(['total_items' => $totalRows]);

        $processed = 0;
        $failed = 0;
        $created = 0;
        $updated = 0;
        $errors = [];
        $rowNum = 0;
        $createdProductIds = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if ($rowNum % 100 === 0 && Cache::get("bulk_job_cancelled_{$this->jobId}")) {
                $job->markAsCancelled();
                fclose($handle);

                return;
            }

            try {
                $data = $this->mapRow($row, $columnIndices, $categories, $brands);
                if (empty($data['sku'])) {
                    throw new \Exception('SKU required');
                }

                $existing = Product::where('organization_id', $this->organizationId)->where('sku', $data['sku'])->first();
                if ($existing) {
                    if ($this->updateExisting) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        throw new \Exception("SKU {$data['sku']} exists");
                    }
                } else {
                    $data['organization_id'] = $this->organizationId;
                    $data['created_by'] = $this->userId;
                    $data['slug'] = Str::slug($data['name'].'-'.$data['sku']);
                    $product = Product::create($data);
                    $createdProductIds[] = $product->id;

                    // Create InventoryLevel if location provided and stock > 0
                    if ($this->locationId && ($data['stock_quantity'] ?? 0) > 0) {
                        InventoryLevel::firstOrCreate(
                            [
                                'organization_id' => $this->organizationId,
                                'product_id' => $product->id,
                                'location_id' => $this->locationId,
                            ],
                            [
                                'quantity_on_hand' => $data['stock_quantity'],
                                'quantity_reserved' => 0,
                                'average_cost' => $data['cost_price'] ?? 0,
                                'last_movement_at' => now(),
                            ]
                        );
                    }

                    $created++;
                }
                $processed++;
            } catch (\Exception $e) {
                $failed++;
                if (count($errors) < 100) {
                    $errors[] = ['row' => $rowNum + 1, 'message' => $e->getMessage()];
                }
                if (! $this->skipErrors) {
                    $job->markAsFailed("Row $rowNum: ".$e->getMessage());
                    fclose($handle);

                    return;
                }
            }

            if ($rowNum % 100 === 0) {
                $job->update(['processed_items' => $processed, 'failed_items' => $failed, 'errors' => $errors]);
                event(new BulkOperationProgress($this->jobId, $this->organizationId, $this->userId, $processed + $failed, $totalRows, $failed));
            }
        }
        fclose($handle);

        $status = $failed === $job->total_items ? BulkOperationJob::STATUS_FAILED : BulkOperationJob::STATUS_COMPLETED;
        $job->update(['status' => $status, 'completed_at' => now(), 'processed_items' => $processed, 'failed_items' => $failed,
            'parameters' => array_merge($job->parameters ?? [], ['created' => $created, 'updated' => $updated])]);
        event(new BulkOperationCompleted($this->jobId, $this->organizationId, $this->userId, 'import', $job->total_items, $processed, $failed, $status));

        // Trigger labeling work order for newly created products
        if (! empty($createdProductIds) && $this->locationId) {
            try {
                app(LabelingWorkflowService::class)->createFromImportJob($job, $createdProductIds, $this->locationId);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to create labeling WO from import', [
                    'job_id' => $this->jobId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function mapRow(array $row, array $indices, array $categories, array $brands): array
    {
        $data = $this->defaultValues;
        $maps = [
            'sku' => fn ($v) => trim($v), 'name' => fn ($v) => trim($v),
            'category' => fn ($v) => $categories[trim($v)] ?? null,
            'brand' => fn ($v) => $brands[trim($v)] ?? null,
            'cost_price' => fn ($v) => round((float) preg_replace('/[^0-9.-]/', '', $v), 2),
            'selling_price' => fn ($v) => round((float) preg_replace('/[^0-9.-]/', '', $v), 2),
            'stock_quantity' => fn ($v) => (int) $v,
            'status' => fn ($v) => strtolower(trim($v)) ?: 'draft',
        ];

        foreach ($indices as $field => $idx) {
            if (isset($row[$idx]) && $row[$idx] !== '') {
                $val = isset($maps[$field]) ? $maps[$field]($row[$idx]) : $row[$idx];
                if ($field === 'category') {
                    $data['category_id'] = $val;
                } elseif ($field === 'brand') {
                    $data['brand_id'] = $val;
                } else {
                    $data[$field] = $val;
                }
            }
        }

        return $data;
    }

    public function failed(\Throwable $e): void
    {
        BulkOperationJob::find($this->jobId)?->markAsFailed($e->getMessage());
    }
}
